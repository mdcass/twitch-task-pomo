const PREVIEW_TIMEOUT_MS = 4500;

const createPreviewSession = (token, widgetId) => ({
    token,
    widgetId,
    status: 'idle',
    timeoutId: null,
    frame: null,
    cleanup: null,
});

const clearPreviewTimeout = (session) => {
    if (session?.timeoutId !== null) {
        window.clearTimeout(session.timeoutId);
        session.timeoutId = null;
    }
};

const cleanupPreviewFrame = (session) => {
    session?.cleanup?.();
    session.cleanup = null;
    session.frame = null;
};

export const createPreviewSessions = ({
    getCopy,
    getWidgets,
    getWidgetGeometry,
    getPreviewElement,
    clonePlaceholderTemplate,
    cloneRuntimeMessageTemplate,
    onRuntimeStateChange,
}) => {
    const previewSessions = new Map();

    const runtimeStateForStatus = (status) => {
        const copy = getCopy();
        const states = copy.runtimeStates ?? {};
        const fallback = states.idle ?? { label: 'Idle', message: null };
        const state = states[status] ?? fallback;

        return {
            status,
            label: state.label ?? fallback.label ?? 'Idle',
            message: state.message ?? null,
        };
    };

    const previewSignatureFor = (widget) =>
        JSON.stringify({
            cropBottom: widget.cropBottom,
            cropLeft: widget.cropLeft,
            cropRight: widget.cropRight,
            cropTop: widget.cropTop,
            contentHeight: widget.contentHeight,
            contentWidth: widget.contentWidth,
            hasPreviewFailure: widget.hasPreviewFailure,
            name: widget.name,
            positionX: widget.positionX,
            positionY: widget.positionY,
            previewMessage: widget.previewMessage,
            previewMode: widget.previewMode,
            previewToken: widget.previewToken,
            previewUrl: widget.previewUrl,
            width: widget.width,
            height: widget.height,
        });

    const visibleContentWidth = (geometry) => Math.max(1, geometry.contentWidth - geometry.cropLeft - geometry.cropRight);
    const visibleContentHeight = (geometry) =>
        Math.max(1, geometry.contentHeight - geometry.cropTop - geometry.cropBottom);

    const runtimeStateForWidget = (widgetId) => {
        const widget = getWidgets().find((candidate) => candidate.id === widgetId);

        if (!widget?.previewToken) {
            return runtimeStateForStatus('idle');
        }

        return runtimeStateForStatus(previewSessions.get(widget.previewToken)?.status ?? 'idle');
    };

    const ensureRuntimeMessageContainer = (preview) => {
        let container = preview.querySelector('[data-widget-runtime-preview-container]');

        if (container instanceof HTMLElement) {
            return container;
        }

        preview.appendChild(cloneRuntimeMessageTemplate());

        container = preview.querySelector('[data-widget-runtime-preview-container]');

        return container instanceof HTMLElement ? container : null;
    };

    const renderWidgetRuntimeMessage = (widgetId, preview) => {
        const container = ensureRuntimeMessageContainer(preview);
        const runtimeMessage = container?.querySelector('[data-widget-runtime-preview-message]');
        const copyElement = container?.querySelector('[data-widget-runtime-preview-copy]');
        const state = runtimeStateForWidget(widgetId);

        if (!(runtimeMessage instanceof HTMLElement) || !(copyElement instanceof HTMLElement)) {
            return;
        }

        runtimeMessage.classList.toggle('d-none', !state.message);
        copyElement.textContent = state.message ?? '';
    };

    const renderRuntimeState = (widgetId) => {
        const preview = getPreviewElement(widgetId);

        if (preview instanceof HTMLElement) {
            renderWidgetRuntimeMessage(widgetId, preview);
        }

        onRuntimeStateChange(widgetId);
    };

    const setPreviewSessionStatus = (widgetId, token, nextStatus) => {
        if (!token) {
            return;
        }

        const session = previewSessions.get(token) ?? createPreviewSession(token, widgetId);

        session.widgetId = widgetId;

        if (nextStatus === 'loaded') {
            clearPreviewTimeout(session);
            session.status = 'loaded';
        } else if (nextStatus === 'error') {
            clearPreviewTimeout(session);
            session.status = session.status === 'loaded' ? 'loaded' : 'error';
        } else if (nextStatus === 'timeout') {
            clearPreviewTimeout(session);
            session.status = session.status === 'loaded' ? 'loaded' : 'timeout';
        } else {
            session.status = 'loading';
        }

        previewSessions.set(token, session);
        renderRuntimeState(widgetId);
    };

    const handlePreviewMessage = (event) => {
        const detail = event.data;

        if (!detail || detail.type !== 'overlay-widget-preview') {
            return;
        }

        const widgetId = Number(detail.widgetId || 0);
        const token = String(detail.token || '');
        const previewEvent = String(detail.event || '');
        const widget = getWidgets().find((candidate) => candidate.previewToken === token);

        if (!widgetId || !token || !widget?.previewUrl) {
            return;
        }

        try {
            if (new URL(widget.previewUrl).origin !== event.origin) {
                return;
            }
        } catch {
            return;
        }

        if (previewEvent === 'loaded' || previewEvent === 'error' || previewEvent === 'timeout') {
            setPreviewSessionStatus(widgetId, token, previewEvent);
        }
    };

    const applyPreviewGeometry = (preview, geometry) => {
        const viewport = preview.querySelector('[data-widget-preview-viewport]');
        const offset = preview.querySelector('[data-widget-preview-offset]');
        const scale = preview.querySelector('[data-widget-preview-scale]');
        const frame = preview.querySelector('iframe[data-widget-preview-iframe]');

        if (
            !(viewport instanceof HTMLElement) ||
            !(offset instanceof HTMLElement) ||
            !(scale instanceof HTMLElement) ||
            !(frame instanceof HTMLIFrameElement)
        ) {
            return;
        }

        const visibleWidth = visibleContentWidth(geometry);
        const visibleHeight = visibleContentHeight(geometry);
        const scaleX = geometry.width / visibleWidth;
        const scaleY = geometry.height / visibleHeight;

        offset.style.width = `${geometry.contentWidth * scaleX}px`;
        offset.style.height = `${geometry.contentHeight * scaleY}px`;
        offset.style.transform = `translate(${-geometry.cropLeft * scaleX}px, ${-geometry.cropTop * scaleY}px)`;
        scale.style.width = `${geometry.contentWidth}px`;
        scale.style.height = `${geometry.contentHeight}px`;
        scale.style.transform = `scale(${scaleX}, ${scaleY})`;
        frame.style.width = `${geometry.contentWidth}px`;
        frame.style.height = `${geometry.contentHeight}px`;
    };

    const bindPreviewFrame = (frame, widget) => {
        if (!(frame instanceof HTMLIFrameElement) || !widget.previewToken) {
            return;
        }

        const session = previewSessions.get(widget.previewToken) ?? createPreviewSession(widget.previewToken, widget.id);

        session.widgetId = widget.id;
        previewSessions.set(widget.previewToken, session);

        if (session.frame === frame) {
            return;
        }

        cleanupPreviewFrame(session);
        session.frame = frame;

        const markError = () => setPreviewSessionStatus(widget.id, widget.previewToken, 'error');
        const markLoaded = () => setPreviewSessionStatus(widget.id, widget.previewToken, 'loaded');

        frame.addEventListener('error', markError, { once: true });

        if (widget.previewMode === 'proprietary') {
            frame.addEventListener('load', markLoaded, { once: true });
        }

        session.cleanup = () => {
            frame.removeEventListener('error', markError);

            if (widget.previewMode === 'proprietary') {
                frame.removeEventListener('load', markLoaded);
            }
        };

        if (session.status === 'idle') {
            session.status = 'loading';
            session.timeoutId = window.setTimeout(() => {
                if (session.status !== 'loaded' && session.status !== 'error') {
                    setPreviewSessionStatus(widget.id, widget.previewToken, 'timeout');
                }
            }, PREVIEW_TIMEOUT_MS);
        }

        renderRuntimeState(widget.id);
    };

    const renderPlaceholder = (preview, widget) => {
        if (widget.previewToken) {
            const session = previewSessions.get(widget.previewToken);

            if (session) {
                clearPreviewTimeout(session);
                cleanupPreviewFrame(session);
                previewSessions.delete(widget.previewToken);
            }
        }

        preview.innerHTML = '';

        const fragment = clonePlaceholderTemplate();
        const name = fragment.querySelector('[data-placeholder-name]');
        const message = fragment.querySelector('[data-placeholder-message]');
        const size = fragment.querySelector('[data-placeholder-size]');
        const position = fragment.querySelector('[data-placeholder-position]');

        if (name instanceof HTMLElement) {
            name.textContent = widget.name;
        }

        if (message instanceof HTMLElement) {
            const copy = getCopy();
            message.textContent = widget.hasPreviewFailure
                ? widget.previewMessage ?? copy.previewFailureFallback
                : copy.previewUnavailable;
        }

        if (size instanceof HTMLElement) {
            size.textContent = `${widget.width} x ${widget.height}`;
        }

        if (position instanceof HTMLElement) {
            position.textContent = `x: ${widget.positionX} / y: ${widget.positionY}`;
        }

        preview.appendChild(fragment);
    };

    const syncWidgetPreview = (widget, preview) => {
        const nextSignature = previewSignatureFor(widget);

        if (preview.dataset.widgetPreviewSignature === nextSignature) {
            return;
        }

        const canRenderFrame =
            typeof widget.previewUrl === 'string' &&
            widget.previewUrl !== '' &&
            widget.hasPreviewFailure === false;

        if (!canRenderFrame) {
            renderPlaceholder(preview, widget);
            preview.dataset.widgetPreviewSignature = nextSignature;

            return;
        }

        let viewport = preview.querySelector('[data-widget-preview-viewport]');
        let offset = preview.querySelector('[data-widget-preview-offset]');
        let scale = preview.querySelector('[data-widget-preview-scale]');
        let frame = preview.querySelector('iframe[data-widget-preview-iframe]');
        const needsNewFrame =
            !(viewport instanceof HTMLElement) ||
            !(offset instanceof HTMLElement) ||
            !(scale instanceof HTMLElement) ||
            !(frame instanceof HTMLIFrameElement) ||
            frame.getAttribute('src') !== widget.previewUrl ||
            frame.dataset.widgetPreviewToken !== widget.previewToken;

        if (needsNewFrame) {
            preview.innerHTML = '';
            viewport = document.createElement('div');
            viewport.className = 'composer-widget__viewport';
            viewport.dataset.widgetPreviewViewport = 'true';
            offset = document.createElement('div');
            offset.className = 'composer-widget__offset';
            offset.dataset.widgetPreviewOffset = 'true';
            scale = document.createElement('div');
            scale.className = 'composer-widget__scale';
            scale.dataset.widgetPreviewScale = 'true';
            frame = document.createElement('iframe');
            frame.className = 'composer-widget__iframe';
            frame.src = widget.previewUrl;
            frame.tabIndex = -1;
            frame.ariaHidden = 'true';
            frame.loading = 'eager';
            frame.setAttribute('sandbox', 'allow-scripts allow-same-origin');
            frame.setAttribute('allowtransparency', 'true');
            frame.referrerPolicy = 'strict-origin-when-cross-origin';
            frame.dataset.widgetPreviewIframe = 'true';
            frame.dataset.widgetId = String(widget.id);
            frame.dataset.widgetPreviewMode = widget.previewMode;
            frame.dataset.widgetPreviewToken = widget.previewToken ?? '';
            scale.appendChild(frame);
            offset.appendChild(scale);
            viewport.appendChild(offset);
            preview.appendChild(viewport);
        }

        preview.dataset.widgetPreviewSignature = nextSignature;
        applyPreviewGeometry(preview, getWidgetGeometry(widget));
        bindPreviewFrame(frame, widget);
        renderWidgetRuntimeMessage(widget.id, preview);
    };

    const prunePreviewSessions = () => {
        const activeTokens = new Set(
            getWidgets()
                .filter((widget) => widget.isVisible && widget.previewToken)
                .map((widget) => widget.previewToken),
        );

        previewSessions.forEach((session, token) => {
            if (activeTokens.has(token)) {
                return;
            }

            clearPreviewTimeout(session);
            cleanupPreviewFrame(session);
            previewSessions.delete(token);
        });
    };

    window.addEventListener('message', handlePreviewMessage);

    return {
        applyPreviewGeometry,
        destroy() {
            window.removeEventListener('message', handlePreviewMessage);

            previewSessions.forEach((session) => {
                clearPreviewTimeout(session);
                cleanupPreviewFrame(session);
            });

            previewSessions.clear();
        },
        getPreviewSessionSnapshot() {
            return Array.from(previewSessions.values()).map((session) => ({
                token: session.token,
                widgetId: session.widgetId,
                status: session.status,
            }));
        },
        prunePreviewSessions,
        runtimeStateForWidget,
        setPreviewSessionStatus,
        syncWidgetPreview,
    };
};

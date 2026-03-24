import { createMoveableInteraction } from './canvas-moveable';
import { createPreviewSessions } from './canvas-preview-sessions';

const INTERACTIVE_VIEWPORT = window.matchMedia('(min-width: 768px)');
const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

class CanvasComposerController {
    constructor(root, wire) {
        this.root = root;
        this.wire = wire;
        this.copy = this.emptyCopy();
        this.snapshot = this.emptySnapshot();
        this.snapshotSource = '';
        this.copySource = '';
        this.widgetElements = new Map();
        this.scheduled = false;
        this.stageObserver = null;
        this.snapshotObserver = null;
        this.observedStage = null;
        this.observedSnapshotNode = null;
        this.observedViewport = null;
        this.viewportResizeObserver =
            typeof ResizeObserver === 'function' ? new ResizeObserver(() => this.scheduleSync()) : null;
        this.viewportLayout = null;
        this.handleStageClick = this.handleStageClick.bind(this);
        this.scheduleSync = this.scheduleSync.bind(this);
        this.previewSessions = createPreviewSessions({
            getCopy: () => this.copy,
            getWidgets: () => this.snapshot.widgets,
            getWidgetGeometry: (widget) => this.widgetGeometry(widget),
            getPreviewElement: (widgetId) => this.widgetElements.get(widgetId)?.querySelector('[data-widget-preview]') ?? null,
            clonePlaceholderTemplate: () => this.cloneTemplate('[data-composer-placeholder-template]'),
            cloneRuntimeMessageTemplate: () => this.cloneTemplate('[data-composer-runtime-message-template]'),
            onRuntimeStateChange: () => this.syncInspectorRuntimeState(),
        });
        this.moveableInteraction = createMoveableInteraction({
            getSnapshot: () => this.snapshot,
            getWidgetByElement: (element) => this.widgetForElement(element),
            getWidgetGeometry: (widget) => this.widgetGeometry(widget),
            normalizeGeometry: (geometry) => this.normalizeDraftGeometry(geometry),
            onGeometryDraft: (widgetId, geometry, target) => {
                const preview = target.querySelector('[data-widget-preview]');

                if (preview instanceof HTMLElement) {
                    this.previewSessions.applyPreviewGeometry(preview, geometry);
                }

                const widget = this.widgetForId(widgetId);

                if (widget) {
                    Object.assign(widget, geometry);
                }
            },
            onGeometryCommit: (widgetId, geometry) => this.call('saveGeometry', widgetId, this.serializeGeometry(geometry)),
            onModifierChange: () => {
                this.syncShortcutState();
                this.syncSelectionState();
            },
        });
    }

    init() {
        this.root.__canvasComposerController = this;
        window.addEventListener('resize', this.scheduleSync);
        INTERACTIVE_VIEWPORT.addEventListener('change', this.scheduleSync);
        this.observeMutations();
        this.sync();
    }

    emptyCopy(copy = {}) {
        return {
            previewUnavailable: String(copy.previewUnavailable ?? 'Preview unavailable for this widget.'),
            previewFailureFallback: String(copy.previewFailureFallback ?? 'This widget could not be previewed in the editor.'),
            runtimeStates: copy.runtimeStates ?? {
                idle: { label: 'Idle', message: null },
                loading: { label: 'Loading', message: 'Editor preview is loading in this browser session.' },
                loaded: { label: 'Loaded', message: null },
                timeout: { label: 'Slow', message: 'Editor preview is taking longer than expected in this browser session.' },
                error: { label: 'Error', message: 'Editor preview could not be confirmed in this browser session.' },
            },
        };
    }

    emptySnapshot(snapshot = {}) {
        const geometryLimits = snapshot.geometryLimits ?? {};

        return {
            selectedWidgetId: Number(snapshot.selectedWidgetId || 0) || null,
            canEdit: snapshot.canEdit === true,
            canvasWidth: Number(snapshot.canvasWidth || 1),
            canvasHeight: Number(snapshot.canvasHeight || 1),
            geometryLimits: {
                minWidth: Number(geometryLimits.minWidth || 120),
                minHeight: Number(geometryLimits.minHeight || 90),
                maxContentWidth: Number(geometryLimits.maxContentWidth || 3840),
                maxContentHeight: Number(geometryLimits.maxContentHeight || 2160),
            },
            widgets: Array.isArray(snapshot.widgets)
                ? snapshot.widgets.map((widget) => ({
                      id: Number(widget?.id || 0),
                      name: String(widget?.name || ''),
                      isVisible: widget?.isVisible === true,
                      previewStatus: String(widget?.previewStatus || ''),
                      previewMessage: widget?.previewMessage ? String(widget.previewMessage) : null,
                      hasPreviewFailure: widget?.hasPreviewFailure === true,
                      positionX: Number(widget?.positionX || 0),
                      positionY: Number(widget?.positionY || 0),
                      width: Number(widget?.width || 0),
                      height: Number(widget?.height || 0),
                      contentWidth: Number(widget?.contentWidth || 0),
                      contentHeight: Number(widget?.contentHeight || 0),
                      cropTop: Number(widget?.cropTop || 0),
                      cropRight: Number(widget?.cropRight || 0),
                      cropBottom: Number(widget?.cropBottom || 0),
                      cropLeft: Number(widget?.cropLeft || 0),
                      zIndex: Number(widget?.zIndex || 0),
                      previewUrl: widget?.previewUrl ? String(widget.previewUrl) : null,
                      previewToken: widget?.previewToken ? String(widget.previewToken) : null,
                      previewMode: String(widget?.previewMode || ''),
                  }))
                : [],
        };
    }

    observeMutations() {
        this.stageObserver = new MutationObserver(this.scheduleSync);
        this.snapshotObserver = new MutationObserver(this.scheduleSync);
        this.bindObservedNodes();
    }

    bindObservedNodes() {
        if (this.observedStage !== this.stage) {
            this.observedStage?.removeEventListener('click', this.handleStageClick);
            this.stageObserver?.disconnect();
            this.observedStage = this.stage instanceof HTMLElement ? this.stage : null;
            this.observedStage?.addEventListener('click', this.handleStageClick);

            if (this.observedStage) {
                this.stageObserver?.observe(this.observedStage, { childList: true });
            }
        }

        if (this.observedSnapshotNode !== this.snapshotNode) {
            this.snapshotObserver?.disconnect();
            this.observedSnapshotNode = this.snapshotNode instanceof HTMLScriptElement ? this.snapshotNode : null;

            if (this.observedSnapshotNode) {
                this.snapshotObserver?.observe(this.observedSnapshotNode, {
                    childList: true,
                    characterData: true,
                    subtree: true,
                });
            }
        }

        if (this.observedViewport !== this.viewport) {
            if (this.observedViewport && this.viewportResizeObserver) {
                this.viewportResizeObserver.unobserve(this.observedViewport);
            }

            this.observedViewport = this.viewport instanceof HTMLElement ? this.viewport : null;

            if (this.observedViewport && this.viewportResizeObserver) {
                this.viewportResizeObserver.observe(this.observedViewport);
            }
        }
    }

    cloneTemplate(selector) {
        const template = this.root.querySelector(selector);

        return template instanceof HTMLTemplateElement ? template.content.cloneNode(true) : document.createDocumentFragment();
    }

    cloneTemplateElement(selector, fallbackSelector) {
        const fragment = this.cloneTemplate(selector);
        const element = fragment.querySelector(fallbackSelector);

        return element instanceof HTMLElement ? element : null;
    }

    scheduleSync() {
        if (this.scheduled) {
            return;
        }

        this.scheduled = true;
        requestAnimationFrame(() => {
            this.scheduled = false;
            this.sync();
        });
    }

    sync() {
        if (!this.root.isConnected) {
            return;
        }

        this.viewport = this.root.querySelector('[data-composer-viewport]');
        this.viewportContent = this.root.querySelector('[data-composer-viewport-content]');
        this.stage = this.root.querySelector('[data-composer-stage]');
        this.moveableLayer = this.root.querySelector('[data-composer-moveable-layer]');
        this.snapshotNode = this.root.querySelector('[data-composer-snapshot]');
        this.copyNode = this.root.querySelector('[data-composer-copy]');
        this.bindObservedNodes();

        if (
            !(this.viewport instanceof HTMLElement) ||
            !(this.viewportContent instanceof HTMLElement) ||
            !(this.stage instanceof HTMLElement) ||
            !(this.snapshotNode instanceof HTMLScriptElement)
        ) {
            return;
        }

        this.copy = this.readCopy();
        this.snapshot = this.readSnapshot();
        this.root.dataset.canEdit = this.snapshot.canEdit ? 'true' : 'false';
        this.root.dataset.selectedWidgetId = this.snapshot.selectedWidgetId ? String(this.snapshot.selectedWidgetId) : '';
        this.stage.classList.toggle('is-readonly', !this.snapshot.canEdit);
        this.updateViewportLayout();

        this.renderStage();
        this.syncSelectionState();
        this.syncShortcutState();
        this.syncInspectorRuntimeState();
        this.moveableInteraction.sync({
            canInteract: this.snapshot.canEdit === true && INTERACTIVE_VIEWPORT.matches,
            stage: this.stage,
            container: this.moveableLayer,
            target: this.resolveSelectedTarget(),
        });
    }

    computeFitZoom() {
        if (!(this.viewport instanceof HTMLElement)) {
            return 1;
        }

        const viewportWidth = Math.max(1, this.viewport.clientWidth);
        const viewportHeight = Math.max(1, this.viewport.clientHeight);
        const canvasWidth = Math.max(1, this.snapshot.canvasWidth);
        const canvasHeight = Math.max(1, this.snapshot.canvasHeight);

        return Math.min(viewportWidth / canvasWidth, viewportHeight / canvasHeight);
    }

    updateViewportLayout() {
        if (
            !(this.viewport instanceof HTMLElement) ||
            !(this.viewportContent instanceof HTMLElement) ||
            !(this.stage instanceof HTMLElement)
        ) {
            return;
        }

        const fitScale = this.computeFitZoom();
        const viewportWidth = Math.max(1, this.viewport.clientWidth);
        const viewportHeight = Math.max(1, this.viewport.clientHeight);
        const stageWidth = Math.max(1, this.snapshot.canvasWidth * fitScale);
        const stageHeight = Math.max(1, this.snapshot.canvasHeight * fitScale);

        this.viewportContent.style.width = '';
        this.viewportContent.style.height = '';
        this.stage.style.width = `${stageWidth}px`;
        this.stage.style.height = `${stageHeight}px`;
        this.stage.style.marginLeft = '0';
        this.stage.style.marginTop = '0';
        this.viewportLayout = {
            fitScale,
            viewportWidth,
            viewportHeight,
            stageWidth,
            stageHeight,
        };
    }

    readSnapshot() {
        const source = this.snapshotNode?.textContent?.trim() ?? '';

        if (source === '' || source === this.snapshotSource) {
            return this.snapshot;
        }

        try {
            this.snapshotSource = source;

            return this.emptySnapshot(JSON.parse(source));
        } catch {
            return this.snapshot;
        }
    }

    readCopy() {
        const source = this.copyNode?.textContent?.trim() ?? '';

        if (source === '' || source === this.copySource) {
            return this.copy;
        }

        try {
            this.copySource = source;

            return this.emptyCopy(JSON.parse(source));
        } catch {
            return this.copy;
        }
    }

    widgetForId(widgetId) {
        return this.snapshot.widgets.find((candidate) => candidate.id === widgetId) ?? null;
    }

    widgetForElement(element) {
        return this.widgetForId(Number(element?.dataset.widgetId || 0));
    }

    widgetGeometry(widget) {
        return this.normalizeDraftGeometry(widget ?? {});
    }

    normalizeDraftGeometry(geometry) {
        const limits = this.snapshot.geometryLimits;
        const width = clamp(Math.round(geometry.width || 0), limits.minWidth, this.snapshot.canvasWidth);
        const height = clamp(Math.round(geometry.height || 0), limits.minHeight, this.snapshot.canvasHeight);
        const contentWidth = clamp(Math.round(geometry.contentWidth || 1), 1, limits.maxContentWidth);
        const contentHeight = clamp(Math.round(geometry.contentHeight || 1), 1, limits.maxContentHeight);

        return {
            positionX: clamp(Math.round(geometry.positionX || 0), 0, this.snapshot.canvasWidth - width),
            positionY: clamp(Math.round(geometry.positionY || 0), 0, this.snapshot.canvasHeight - height),
            width,
            height,
            contentWidth,
            contentHeight,
            cropTop: clamp(Math.round(geometry.cropTop || 0), 0, Math.max(0, contentHeight - 1)),
            cropRight: clamp(Math.round(geometry.cropRight || 0), 0, Math.max(0, contentWidth - 1)),
            cropBottom: clamp(Math.round(geometry.cropBottom || 0), 0, Math.max(0, contentHeight - 1)),
            cropLeft: clamp(Math.round(geometry.cropLeft || 0), 0, Math.max(0, contentWidth - 1)),
        };
    }

    serializeGeometry(geometry) {
        return {
            position_x: geometry.positionX,
            position_y: geometry.positionY,
            width: geometry.width,
            height: geometry.height,
            content_width: geometry.contentWidth,
            content_height: geometry.contentHeight,
            crop_top: geometry.cropTop,
            crop_right: geometry.cropRight,
            crop_bottom: geometry.cropBottom,
            crop_left: geometry.cropLeft,
        };
    }

    renderStage() {
        const activeWidgetIds = new Set();
        const visibleWidgets = this.snapshot.widgets.filter((widget) => widget.isVisible);

        this.widgetElements.forEach((element, widgetId) => {
            if (!element.isConnected) {
                this.widgetElements.delete(widgetId);
            }
        });

        visibleWidgets.forEach((widget) => {
            activeWidgetIds.add(widget.id);

            const element = this.ensureWidgetElement(widget.id);

            if (element) {
                this.updateWidgetElement(widget, element);
                element.hidden = false;
            }
        });

        this.widgetElements.forEach((element, widgetId) => {
            if (activeWidgetIds.has(widgetId)) {
                return;
            }

            element.remove();
            this.widgetElements.delete(widgetId);
        });

        this.renderEmptyState(visibleWidgets.length === 0);
        this.previewSessions.prunePreviewSessions();
    }

    ensureWidgetElement(widgetId) {
        let element = this.widgetElements.get(widgetId);

        if (element?.isConnected) {
            return element;
        }

        element = this.cloneTemplateElement('[data-composer-widget-template]', '[data-widget-id]');

        if (!(element instanceof HTMLElement)) {
            return null;
        }

        element.dataset.widgetId = String(widgetId);
        this.stage.insertBefore(element, this.moveableLayer ?? null);
        this.widgetElements.set(widgetId, element);

        return element;
    }

    renderEmptyState(show) {
        let emptyState = this.stage.querySelector('[data-composer-empty-state]');

        if (!show) {
            emptyState?.remove();
            return;
        }

        if (emptyState instanceof HTMLElement) {
            return;
        }

        emptyState = this.cloneTemplateElement('[data-composer-empty-state-template]', '[data-composer-empty-state]');

        if (emptyState instanceof HTMLElement) {
            this.stage.insertBefore(emptyState, this.moveableLayer ?? null);
        }
    }

    updateWidgetElement(widget, element) {
        const left = `${(widget.positionX / this.snapshot.canvasWidth) * this.stage.clientWidth}px`;
        const top = `${(widget.positionY / this.snapshot.canvasHeight) * this.stage.clientHeight}px`;
        const width = `${(widget.width / this.snapshot.canvasWidth) * this.stage.clientWidth}px`;
        const height = `${(widget.height / this.snapshot.canvasHeight) * this.stage.clientHeight}px`;

        element.dataset.widgetPreviewStatus = widget.previewStatus;
        element.style.left = left;
        element.style.top = top;
        element.style.width = width;
        element.style.height = height;
        element.style.zIndex = String(widget.zIndex);
        const preview = element.querySelector('[data-widget-preview]');

        if (preview instanceof HTMLElement) {
            this.previewSessions.syncWidgetPreview(widget, preview);
        }
    }

    syncSelectionState() {
        const modifierState = this.moveableInteraction.getModifierState();
        const interactionMode = this.moveableInteraction.getInteractionMode();

        this.widgetElements.forEach((element, widgetId) => {
            const isSelected = this.snapshot.selectedWidgetId === widgetId;
            element.classList.toggle('is-selected', isSelected);
            element.classList.toggle('is-crop-mode', isSelected && (modifierState.alt || interactionMode === 'crop'));
            element.classList.toggle('is-source-mode', isSelected && (modifierState.source || interactionMode === 'source'));
        });
    }

    syncShortcutState() {
        const modifierState = this.moveableInteraction.getModifierState();

        this.root.dataset.cropModifierActive = modifierState.alt ? 'true' : 'false';
        this.root.dataset.sourceModifierActive = modifierState.source ? 'true' : 'false';
        this.root.dataset.stretchModifierActive = modifierState.shift ? 'true' : 'false';
        this.root.querySelectorAll('[data-composer-shortcut]').forEach((element) => {
            if (!(element instanceof HTMLElement)) {
                return;
            }

            const shortcut = element.dataset.composerShortcut;
            const isActive =
                (shortcut === 'resize' && !modifierState.alt && !modifierState.source && !modifierState.shift) ||
                (shortcut === 'crop' && modifierState.alt) ||
                (shortcut === 'source' && modifierState.source) ||
                (shortcut === 'stretch' && modifierState.shift);

            element.classList.toggle('is-active', isActive);
            element.classList.toggle('is-crop-active', shortcut === 'crop' && modifierState.alt);
            element.classList.toggle('is-source-active', shortcut === 'source' && modifierState.source);
        });
    }

    syncInspectorRuntimeState() {
        const label = this.root.querySelector('[data-composer-runtime-label]');
        const message = this.root.querySelector('[data-composer-runtime-message]');
        const runtimeState = this.snapshot.selectedWidgetId
            ? this.previewSessions.runtimeStateForWidget(this.snapshot.selectedWidgetId)
            : this.copy.runtimeStates?.idle ?? { label: 'Idle', message: null };

        if (label instanceof HTMLElement) {
            label.textContent = runtimeState.label ?? 'Idle';
        }

        if (message instanceof HTMLElement) {
            message.textContent = runtimeState.message ?? '';
            message.classList.toggle('d-none', !runtimeState.message);
        }
    }

    resolveSelectedTarget() {
        return this.snapshot.selectedWidgetId ? this.widgetElements.get(this.snapshot.selectedWidgetId) ?? null : null;
    }

    handleStageClick(event) {
        const widget = event.target.closest('[data-widget-id]');
        const widgetId = Number(widget?.dataset.widgetId || 0);

        if (!(widget instanceof HTMLElement) || !widgetId || this.snapshot.selectedWidgetId === widgetId) {
            return;
        }

        this.snapshot.selectedWidgetId = widgetId;
        this.root.dataset.selectedWidgetId = String(widgetId);
        this.syncSelectionState();
        this.syncInspectorRuntimeState();
        this.moveableInteraction.sync({
            canInteract: this.snapshot.canEdit === true && INTERACTIVE_VIEWPORT.matches,
            stage: this.stage,
            container: this.moveableLayer,
            target: this.resolveSelectedTarget(),
        });
        this.call('selectWidget', widgetId);
    }

    call(method, ...args) {
        this.wire?.$call?.(method, ...args);
    }

    setModifierState(state) {
        this.moveableInteraction.setModifierState(state);
    }

    setPreviewSessionStatus(widgetId, token, status) {
        this.previewSessions.setPreviewSessionStatus(widgetId, token, status);
    }

    getPreviewSessionSnapshot() {
        return this.previewSessions.getPreviewSessionSnapshot();
    }

    get moveable() {
        return this.moveableInteraction.moveable;
    }

    get currentTargetId() {
        return this.moveableInteraction.currentTargetId;
    }

    getViewState() {
        return {
            fitScale: this.viewportLayout?.fitScale ?? 0,
            viewportWidth: this.viewportLayout?.viewportWidth ?? 0,
            viewportHeight: this.viewportLayout?.viewportHeight ?? 0,
            stageWidth: this.viewportLayout?.stageWidth ?? 0,
            stageHeight: this.viewportLayout?.stageHeight ?? 0,
        };
    }

    destroy() {
        window.removeEventListener('resize', this.scheduleSync);
        INTERACTIVE_VIEWPORT.removeEventListener('change', this.scheduleSync);
        this.observedStage?.removeEventListener('click', this.handleStageClick);
        if (this.observedViewport && this.viewportResizeObserver) {
            this.viewportResizeObserver.unobserve(this.observedViewport);
        }
        this.viewportResizeObserver?.disconnect?.();
        this.stageObserver?.disconnect();
        this.snapshotObserver?.disconnect();
        this.moveableInteraction.destroy();
        this.previewSessions.destroy();
        this.widgetElements.clear();
        delete this.root.__canvasComposerController;
    }
}

window.__canvasComposerCreate = (element, wire) => new CanvasComposerController(element, wire);

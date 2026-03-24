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
        this.history = [];
        this.redoStack = [];
        this.actionQueue = Promise.resolve();
        this.handleStageClick = this.handleStageClick.bind(this);
        this.handleActionClick = this.handleActionClick.bind(this);
        this.handleWindowKeydown = this.handleWindowKeydown.bind(this);
        this.handleWidgetSetMutation = this.handleWidgetSetMutation.bind(this);
        this.handleWidgetDeleted = this.handleWidgetDeleted.bind(this);
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
            onGeometryCommit: (widgetId, geometry) =>
                this.performHistoryMutation('saveGeometry', widgetId, this.serializeGeometry(geometry)),
            onModifierChange: () => {
                this.syncShortcutState();
                this.syncSelectionState();
            },
        });
    }

    init() {
        this.root.__canvasComposerController = this;
        window.addEventListener('resize', this.scheduleSync);
        window.addEventListener('keydown', this.handleWindowKeydown);
        window.addEventListener('widget-created', this.handleWidgetSetMutation);
        window.addEventListener('widget-deleted-browser', this.handleWidgetDeleted);
        INTERACTIVE_VIEWPORT.addEventListener('change', this.scheduleSync);
        this.root.addEventListener('click', this.handleActionClick);
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
        this.stageCamera = this.root.querySelector('[data-composer-stage-camera]');
        this.stage = this.root.querySelector('[data-composer-stage]');
        this.moveableLayer = this.root.querySelector('[data-composer-moveable-layer]');
        this.snapshotNode = this.root.querySelector('[data-composer-snapshot]');
        this.copyNode = this.root.querySelector('[data-composer-copy]');
        this.bindObservedNodes();

        if (
            !(this.viewport instanceof HTMLElement) ||
            !(this.viewportContent instanceof HTMLElement) ||
            !(this.stageCamera instanceof HTMLElement) ||
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
        this.syncHistoryControls();
        this.syncFitBadge();
        this.moveableInteraction.sync({
            canInteract: this.snapshot.canEdit === true && INTERACTIVE_VIEWPORT.matches,
            stage: this.stage,
            container: this.moveableLayer,
            target: this.resolveSelectedTarget(),
            zoom: this.viewportLayout?.fitScale ?? 1,
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

        return Math.min(1, viewportWidth / canvasWidth, viewportHeight / canvasHeight);
    }

    updateViewportLayout() {
        if (
            !(this.viewport instanceof HTMLElement) ||
            !(this.viewportContent instanceof HTMLElement) ||
            !(this.stageCamera instanceof HTMLElement) ||
            !(this.stage instanceof HTMLElement)
        ) {
            return;
        }

        const fitScale = this.computeFitZoom();
        const viewportWidth = Math.max(1, this.viewport.clientWidth);
        const viewportHeight = Math.max(1, this.viewport.clientHeight);
        const cameraWidth = Math.max(1, Math.round(this.snapshot.canvasWidth * fitScale));
        const cameraHeight = Math.max(1, Math.round(this.snapshot.canvasHeight * fitScale));

        this.viewportContent.style.width = '';
        this.viewportContent.style.height = '';
        this.stageCamera.style.width = `${cameraWidth}px`;
        this.stageCamera.style.height = `${cameraHeight}px`;
        this.stage.style.width = `${this.snapshot.canvasWidth}px`;
        this.stage.style.height = `${this.snapshot.canvasHeight}px`;
        this.stage.style.transform = `scale(${fitScale})`;
        this.viewportLayout = {
            fitScale,
            viewportWidth,
            viewportHeight,
            stageWidth: this.snapshot.canvasWidth,
            stageHeight: this.snapshot.canvasHeight,
            cameraWidth,
            cameraHeight,
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

    captureHistorySnapshot() {
        return {
            widgets: this.snapshot.widgets.map((widget) => ({
                id: widget.id,
                position_x: widget.positionX,
                position_y: widget.positionY,
                width: widget.width,
                height: widget.height,
                content_width: widget.contentWidth,
                content_height: widget.contentHeight,
                crop_top: widget.cropTop,
                crop_right: widget.cropRight,
                crop_bottom: widget.cropBottom,
                crop_left: widget.cropLeft,
                is_visible: widget.isVisible,
            })),
        };
    }

    historySnapshotKey(snapshot) {
        return JSON.stringify(snapshot);
    }

    queueAction(task) {
        const nextTask = this.actionQueue.then(task, task);

        this.actionQueue = nextTask.catch(() => {});

        return nextTask;
    }

    async performHistoryMutation(method, ...args) {
        if (!this.snapshot.canEdit) {
            return null;
        }

        return this.queueAction(async () => {
            const before = this.captureHistorySnapshot();
            const selectedBefore = this.snapshot.selectedWidgetId;

            await this.call(method, ...args);
            this.sync();

            const after = this.captureHistorySnapshot();
            const selectedAfter = this.snapshot.selectedWidgetId;

            if (this.historySnapshotKey(before) === this.historySnapshotKey(after) && selectedBefore === selectedAfter) {
                this.syncHistoryControls();
                return null;
            }

            this.history.push({
                before,
                after,
                selectedBefore,
                selectedAfter,
            });

            if (this.history.length > 50) {
                this.history.splice(0, this.history.length - 50);
            }

            this.redoStack = [];
            this.syncHistoryControls();

            return null;
        });
    }

    async restoreHistoryEntry(entry, direction) {
        if (!entry || !this.snapshot.canEdit) {
            return;
        }

        const targetSnapshot = direction === 'undo' ? entry.before : entry.after;
        const targetSelection = direction === 'undo' ? entry.selectedBefore : entry.selectedAfter;

        await this.call('restoreHistoryState', targetSnapshot.widgets, targetSelection);
        this.sync();
        this.syncHistoryControls();
    }

    async undo() {
        if (!this.history.length) {
            return;
        }

        return this.queueAction(async () => {
            const entry = this.history.pop();

            if (!entry) {
                return;
            }

            await this.restoreHistoryEntry(entry, 'undo');
            this.redoStack.push(entry);
            this.syncHistoryControls();
        });
    }

    async redo() {
        if (!this.redoStack.length) {
            return;
        }

        return this.queueAction(async () => {
            const entry = this.redoStack.pop();

            if (!entry) {
                return;
            }

            await this.restoreHistoryEntry(entry, 'redo');
            this.history.push(entry);
            this.syncHistoryControls();
        });
    }

    clearHistory() {
        this.history = [];
        this.redoStack = [];
        this.syncHistoryControls();
    }

    syncHistoryControls() {
        const canUndo = this.snapshot.canEdit === true && this.history.length > 0;
        const canRedo = this.snapshot.canEdit === true && this.redoStack.length > 0;

        this.root.dataset.canUndo = canUndo ? 'true' : 'false';
        this.root.dataset.canRedo = canRedo ? 'true' : 'false';
        this.root.querySelectorAll('[data-composer-history]').forEach((element) => {
            if (!(element instanceof HTMLButtonElement)) {
                return;
            }

            const direction = element.dataset.composerHistory;
            element.disabled = direction === 'undo' ? !canUndo : !canRedo;
        });
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
        this.stage.appendChild(element);
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
            this.stage.appendChild(emptyState);
        }
    }

    updateWidgetElement(widget, element) {
        element.dataset.widgetPreviewStatus = widget.previewStatus;
        element.style.left = `${widget.positionX}px`;
        element.style.top = `${widget.positionY}px`;
        element.style.width = `${widget.width}px`;
        element.style.height = `${widget.height}px`;
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

    syncFitBadge() {
        const badge = this.root.querySelector('[data-composer-fit-badge]');
        const fitPercent = Math.round((this.viewportLayout?.fitScale ?? 1) * 100);

        if (badge instanceof HTMLElement) {
            badge.textContent = `Fit (${fitPercent}%)`;
        }

        this.root.dataset.fitScale = String(this.viewportLayout?.fitScale ?? 1);
        this.root.dataset.fitPercent = String(fitPercent);
    }

    resolveSelectedTarget() {
        return this.snapshot.selectedWidgetId ? this.widgetElements.get(this.snapshot.selectedWidgetId) ?? null : null;
    }

    handleActionClick(event) {
        const target = event.target instanceof Element ? event.target : null;

        if (!target) {
            return;
        }

        const historyButton = target.closest('[data-composer-history]');

        if (historyButton instanceof HTMLElement && this.root.contains(historyButton)) {
            event.preventDefault();
            event.stopPropagation();

            if (historyButton.dataset.composerHistory === 'undo') {
                void this.undo();
            } else if (historyButton.dataset.composerHistory === 'redo') {
                void this.redo();
            }

            return;
        }

        const resetButton = target.closest('[data-composer-reset]');

        if (resetButton instanceof HTMLElement && this.root.contains(resetButton)) {
            event.preventDefault();
            event.stopPropagation();

            const widgetId = Number(resetButton.dataset.widgetId || this.snapshot.selectedWidgetId || 0);
            const scope = resetButton.dataset.composerReset;

            if (widgetId && scope) {
                void this.performHistoryMutation('resetGeometry', widgetId, scope);
            }

            return;
        }

        const layerAction = target.closest('[data-composer-layer-action], [data-composer-selected-action]');

        if (!(layerAction instanceof HTMLElement) || !this.root.contains(layerAction)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const action = layerAction.dataset.composerLayerAction ?? layerAction.dataset.composerSelectedAction ?? '';
        const widgetId = Number(layerAction.dataset.widgetId || 0);

        if (!widgetId) {
            return;
        }

        if (action === 'visibility') {
            void this.performHistoryMutation('toggleVisibility', widgetId);
            return;
        }

        if (action === 'reorder') {
            const direction = layerAction.dataset.direction;

            if (direction) {
                void this.performHistoryMutation('reorderWidget', widgetId, direction);
            }

            return;
        }

        if (action === 'delete') {
            this.openDeleteModal(widgetId);
        }
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
            zoom: this.viewportLayout?.fitScale ?? 1,
        });
        this.call('selectWidget', widgetId);
    }

    handleWidgetSetMutation() {
        this.clearHistory();
    }

    handleWidgetDeleted(event) {
        this.clearHistory();

        const detail = event instanceof CustomEvent && event.detail && typeof event.detail === 'object' ? event.detail : {};
        const deletedWidgetId = Number(detail.deletedWidgetId || 0) || null;
        const selectedWidgetId = Number(detail.selectedWidgetId || 0) || null;

        void this.queueAction(async () => {
            await this.call('handleWidgetDeleted', deletedWidgetId, selectedWidgetId);
            this.sync();
        });
    }

    hasBlockingOverlayOpen() {
        return document.querySelector('.modal.show, .offcanvas.show') !== null;
    }

    isTypingTarget(target) {
        return (
            target instanceof HTMLElement &&
            (target.closest('input, textarea, select, [contenteditable=""], [contenteditable="true"], [role="textbox"]') !==
                null ||
                target.isContentEditable)
        );
    }

    handleWindowKeydown(event) {
        if (!this.root.isConnected || this.snapshot.canEdit !== true) {
            return;
        }

        const target = event.target instanceof HTMLElement ? event.target : document.activeElement;

        if (this.hasBlockingOverlayOpen() || this.isTypingTarget(target)) {
            return;
        }

        if ((event.metaKey || event.ctrlKey) && !event.altKey && String(event.key).toLowerCase() === 'z') {
            event.preventDefault();

            if (event.shiftKey) {
                void this.redo();
            } else {
                void this.undo();
            }

            return;
        }

        if (event.ctrlKey && !event.metaKey && !event.altKey && String(event.key).toLowerCase() === 'y') {
            event.preventDefault();
            void this.redo();
            return;
        }

        if (
            !event.metaKey &&
            !event.ctrlKey &&
            !event.altKey &&
            (event.key === 'Delete' || event.key === 'Backspace') &&
            this.snapshot.selectedWidgetId
        ) {
            event.preventDefault();
            this.openDeleteModal(this.snapshot.selectedWidgetId);
        }
    }

    openDeleteModal(widgetId) {
        const modalId = this.root.dataset.deleteModalId;

        if (!modalId || !widgetId) {
            return;
        }

        window.dispatchEvent(
            new CustomEvent('overlay-modal-load', {
                detail: {
                    id: modalId,
                    data: {
                        widgetId,
                    },
                },
            }),
        );
    }

    async call(method, ...args) {
        return this.wire?.$call?.(method, ...args);
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

    getHistoryState() {
        return {
            canUndo: this.history.length > 0,
            canRedo: this.redoStack.length > 0,
            undoCount: this.history.length,
            redoCount: this.redoStack.length,
        };
    }

    getViewState() {
        return {
            fitScale: this.viewportLayout?.fitScale ?? 0,
            viewportWidth: this.viewportLayout?.viewportWidth ?? 0,
            viewportHeight: this.viewportLayout?.viewportHeight ?? 0,
            stageWidth: this.viewportLayout?.stageWidth ?? 0,
            stageHeight: this.viewportLayout?.stageHeight ?? 0,
            cameraWidth: this.viewportLayout?.cameraWidth ?? 0,
            cameraHeight: this.viewportLayout?.cameraHeight ?? 0,
        };
    }

    destroy() {
        window.removeEventListener('resize', this.scheduleSync);
        window.removeEventListener('keydown', this.handleWindowKeydown);
        window.removeEventListener('widget-created', this.handleWidgetSetMutation);
        window.removeEventListener('widget-deleted-browser', this.handleWidgetDeleted);
        INTERACTIVE_VIEWPORT.removeEventListener('change', this.scheduleSync);
        this.observedStage?.removeEventListener('click', this.handleStageClick);
        this.root.removeEventListener('click', this.handleActionClick);
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

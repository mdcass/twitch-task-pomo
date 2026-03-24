import Moveable from 'moveable';

const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

export const createMoveableInteraction = ({
    getSnapshot,
    getWidgetByElement,
    getWidgetGeometry,
    normalizeGeometry,
    onGeometryDraft,
    onGeometryCommit,
    onModifierChange,
}) => {
    let stage = null;
    let container = null;
    let moveable = null;
    let currentTargetId = null;
    let activeResize = null;
    let modifierState = {
        alt: false,
        source: false,
        shift: false,
    };

    const visibleContentWidth = (geometry) => Math.max(1, geometry.contentWidth - geometry.cropLeft - geometry.cropRight);
    const visibleContentHeight = (geometry) =>
        Math.max(1, geometry.contentHeight - geometry.cropTop - geometry.cropBottom);
    const contentScaleX = (geometry) => geometry.width / visibleContentWidth(geometry);
    const contentScaleY = (geometry) => geometry.height / visibleContentHeight(geometry);
    const stageWidth = () => stage?.clientWidth || 1;
    const stageHeight = () => stage?.clientHeight || 1;
    const stageLengthToCanvasX = (value) => (value / stageWidth()) * getSnapshot().canvasWidth;
    const stageLengthToCanvasY = (value) => (value / stageHeight()) * getSnapshot().canvasHeight;
    const canvasLengthToStageX = (value) => (value / getSnapshot().canvasWidth) * stageWidth();
    const canvasLengthToStageY = (value) => (value / getSnapshot().canvasHeight) * stageHeight();
    const minimumStageWidth = () => canvasLengthToStageX(getSnapshot().geometryLimits.minWidth);
    const minimumStageHeight = () => canvasLengthToStageY(getSnapshot().geometryLimits.minHeight);
    const currentResizeMode = () =>
        modifierState.alt ? 'crop' : modifierState.source ? 'source' : modifierState.shift ? 'stretch' : 'resize';

    const syncMoveableModifierMode = () => {
        if (!moveable) {
            return;
        }

        moveable.keepRatio = currentResizeMode() === 'resize';
        moveable.updateRect();
    };

    const readStageRect = (target) => {
        const left = parseFloat(target.style.left || '0');
        const top = parseFloat(target.style.top || '0');
        const width = parseFloat(target.style.width || `${target.getBoundingClientRect().width}`);
        const height = parseFloat(target.style.height || `${target.getBoundingClientRect().height}`);

        return {
            left,
            top,
            width,
            height,
            right: left + width,
            bottom: top + height,
        };
    };

    const applyTargetRect = (target, rect) => {
        target.style.left = `${rect.left}px`;
        target.style.top = `${rect.top}px`;
        target.style.width = `${rect.width}px`;
        target.style.height = `${rect.height}px`;
    };

    const stageRectForGeometry = (geometry) => {
        const left = canvasLengthToStageX(geometry.positionX);
        const top = canvasLengthToStageY(geometry.positionY);
        const width = canvasLengthToStageX(geometry.width);
        const height = canvasLengthToStageY(geometry.height);

        return {
            left,
            top,
            width,
            height,
            right: left + width,
            bottom: top + height,
        };
    };

    const buildScaledGeometry = (startGeometry, nextGeometry, leftSourceDelta, topSourceDelta) =>
        normalizeGeometry({
            ...nextGeometry,
            positionX: startGeometry.positionX - leftSourceDelta * contentScaleX(startGeometry),
            positionY: startGeometry.positionY - topSourceDelta * contentScaleY(startGeometry),
            width: visibleContentWidth(nextGeometry) * contentScaleX(startGeometry),
            height: visibleContentHeight(nextGeometry) * contentScaleY(startGeometry),
        });

    const readCropGeometry = (resizeState, rect) => {
        const stageScaleX = resizeState.startRect.width / visibleContentWidth(resizeState.startGeometry);
        const stageScaleY = resizeState.startRect.height / visibleContentHeight(resizeState.startGeometry);
        const draftGeometry = normalizeGeometry({
            ...resizeState.startGeometry,
            cropLeft: resizeState.startGeometry.cropLeft + (rect.left - resizeState.startRect.left) / stageScaleX,
            cropRight: resizeState.startGeometry.cropRight + (resizeState.startRect.right - rect.right) / stageScaleX,
            cropTop: resizeState.startGeometry.cropTop + (rect.top - resizeState.startRect.top) / stageScaleY,
            cropBottom: resizeState.startGeometry.cropBottom + (resizeState.startRect.bottom - rect.bottom) / stageScaleY,
        });

        return buildScaledGeometry(
            resizeState.startGeometry,
            draftGeometry,
            resizeState.startGeometry.cropLeft - draftGeometry.cropLeft,
            resizeState.startGeometry.cropTop - draftGeometry.cropTop,
        );
    };

    const readSourceBoundsGeometry = (resizeState, rect) => {
        const stageScaleX = resizeState.startRect.width / visibleContentWidth(resizeState.startGeometry);
        const stageScaleY = resizeState.startRect.height / visibleContentHeight(resizeState.startGeometry);
        const expandLeft = (resizeState.startRect.left - rect.left) / stageScaleX;
        const expandRight = (rect.right - resizeState.startRect.right) / stageScaleX;
        const expandTop = (resizeState.startRect.top - rect.top) / stageScaleY;
        const expandBottom = (rect.bottom - resizeState.startRect.bottom) / stageScaleY;
        const draftGeometry = normalizeGeometry({
            ...resizeState.startGeometry,
            contentWidth: resizeState.startGeometry.contentWidth + expandLeft + expandRight,
            contentHeight: resizeState.startGeometry.contentHeight + expandTop + expandBottom,
        });

        return buildScaledGeometry(resizeState.startGeometry, draftGeometry, expandLeft, expandTop);
    };

    const readCanvasGeometry = (target) => {
        const widget = getWidgetByElement(target);
        const surfaceRect = stage.getBoundingClientRect();
        const targetRect = target.getBoundingClientRect();

        return normalizeGeometry({
            positionX: stageLengthToCanvasX(targetRect.left - surfaceRect.left),
            positionY: stageLengthToCanvasY(targetRect.top - surfaceRect.top),
            width: stageLengthToCanvasX(targetRect.width),
            height: stageLengthToCanvasY(targetRect.height),
            contentWidth: widget?.contentWidth ?? widget?.width ?? getSnapshot().geometryLimits.minWidth,
            contentHeight: widget?.contentHeight ?? widget?.height ?? getSnapshot().geometryLimits.minHeight,
            cropTop: widget?.cropTop ?? 0,
            cropRight: widget?.cropRight ?? 0,
            cropBottom: widget?.cropBottom ?? 0,
            cropLeft: widget?.cropLeft ?? 0,
        });
    };

    const commitGeometry = (target) => {
        const widgetId = Number(target.dataset.widgetId || 0);

        if (!widgetId) {
            return;
        }

        onGeometryCommit(
            widgetId,
            activeResize?.widgetId === widgetId && activeResize.lastGeometry ? activeResize.lastGeometry : readCanvasGeometry(target),
        );
    };

    const destroyMoveable = () => {
        if (!moveable) {
            return;
        }

        moveable.destroy();
        moveable = null;
        currentTargetId = null;
    };

    const ensureMoveable = (target) => {
        const overlayContainer = container ?? stage;

        moveable = new Moveable(overlayContainer, {
            target,
            draggable: true,
            resizable: true,
            edge: true,
            origin: false,
            keepRatio: true,
            renderDirections: ['nw', 'n', 'ne', 'w', 'e', 'sw', 's', 'se'],
            container: stage,
            rootContainer: stage,
            viewContainer: stage,
            bounds: {
                left: 0,
                top: 0,
                right: stage.clientWidth,
                bottom: stage.clientHeight,
                position: 'css',
            },
        });

        moveable
            .on('drag', ({ target: activeTarget, left, top }) => {
                if (activeTarget instanceof HTMLElement) {
                    activeTarget.style.left = `${left}px`;
                    activeTarget.style.top = `${top}px`;
                }
            })
            .on('dragEnd', ({ target: activeTarget, isDrag }) => {
                if (isDrag && activeTarget instanceof HTMLElement) {
                    commitGeometry(activeTarget);
                }
            })
            .on('resizeStart', ({ target: activeTarget, direction, setRatio }) => {
                const widget = activeTarget instanceof HTMLElement ? getWidgetByElement(activeTarget) : null;

                if (!widget) {
                    return;
                }

                activeResize = {
                    widgetId: widget.id,
                    mode: currentResizeMode(),
                    direction: Array.isArray(direction) ? [...direction] : [0, 0],
                    startGeometry: getWidgetGeometry(widget),
                    startRect: readStageRect(activeTarget),
                    lastGeometry: getWidgetGeometry(widget),
                };

                if (activeResize.mode === 'resize') {
                    setRatio?.(
                        visibleContentWidth(activeResize.startGeometry) / visibleContentHeight(activeResize.startGeometry),
                    );
                }

                onModifierChange({ ...modifierState, mode: activeResize.mode });
            })
            .on('resize', ({ target: activeTarget, width, height, drag }) => {
                if (!(activeTarget instanceof HTMLElement) || !activeResize) {
                    return;
                }

                const rect = {
                    left: drag?.left ?? readStageRect(activeTarget).left,
                    top: drag?.top ?? readStageRect(activeTarget).top,
                    width: Math.max(minimumStageWidth(), width),
                    height: Math.max(minimumStageHeight(), height),
                };
                rect.right = rect.left + rect.width;
                rect.bottom = rect.top + rect.height;
                applyTargetRect(activeTarget, rect);

                activeResize.lastGeometry =
                    activeResize.mode === 'crop'
                        ? readCropGeometry(activeResize, rect)
                        : activeResize.mode === 'source'
                          ? readSourceBoundsGeometry(activeResize, rect)
                          : readCanvasGeometry(activeTarget);

                applyTargetRect(activeTarget, stageRectForGeometry(activeResize.lastGeometry));
                onGeometryDraft(activeResize.widgetId, activeResize.lastGeometry, activeTarget);
            })
            .on('resizeEnd', ({ target: activeTarget, isDrag }) => {
                if (isDrag && activeTarget instanceof HTMLElement) {
                    commitGeometry(activeTarget);
                }

                activeResize = null;
                onModifierChange({ ...modifierState, mode: currentResizeMode() });
            });
    };

    const setModifierState = ({ alt, source, shift }) => {
        const nextState = {
            alt: alt === true,
            source: source === true,
            shift: shift === true,
        };

        if (
            modifierState.alt === nextState.alt &&
            modifierState.source === nextState.source &&
            modifierState.shift === nextState.shift
        ) {
            return;
        }

        modifierState = nextState;
        syncMoveableModifierMode();
        onModifierChange({ ...modifierState, mode: activeResize?.mode ?? currentResizeMode() });
    };

    const handleKeyState = (event) =>
        setModifierState({
            alt: event.altKey,
            source: event.ctrlKey || event.metaKey,
            shift: event.shiftKey,
        });

    const handleWindowBlur = () =>
        setModifierState({
            alt: false,
            source: false,
            shift: false,
        });

    const handleVisibilityChange = () => {
        if (document.visibilityState === 'hidden') {
            handleWindowBlur();
        }
    };

    window.addEventListener('keydown', handleKeyState);
    window.addEventListener('keyup', handleKeyState);
    window.addEventListener('blur', handleWindowBlur);
    document.addEventListener('visibilitychange', handleVisibilityChange);

    return {
        destroy() {
            destroyMoveable();
            window.removeEventListener('keydown', handleKeyState);
            window.removeEventListener('keyup', handleKeyState);
            window.removeEventListener('blur', handleWindowBlur);
            document.removeEventListener('visibilitychange', handleVisibilityChange);
        },
        getInteractionMode() {
            return activeResize?.mode ?? currentResizeMode();
        },
        getModifierState() {
            return { ...modifierState };
        },
        get currentTargetId() {
            return currentTargetId;
        },
        get moveable() {
            return moveable;
        },
        setModifierState,
        sync({ canInteract, stage: nextStage, container: nextContainer, target }) {
            stage = nextStage;
            container = nextContainer;

            if (!canInteract || !(stage instanceof HTMLElement) || !(target instanceof HTMLElement)) {
                destroyMoveable();
                return;
            }

            const targetId = target.dataset.widgetId ?? null;
            const targetChanged = !moveable || currentTargetId !== targetId || moveable.target !== target;

            if (targetChanged) {
                destroyMoveable();
                ensureMoveable(target);
                currentTargetId = targetId;
            }

            syncMoveableModifierMode();
            moveable.bounds = {
                left: 0,
                top: 0,
                right: stage.clientWidth,
                bottom: stage.clientHeight,
                position: 'css',
            };
            moveable.updateRect();
        },
    };
};

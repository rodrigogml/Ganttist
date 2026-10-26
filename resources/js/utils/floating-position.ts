export type FloatingRect = {
    top: number;
    right: number;
    bottom: number;
    left: number;
};

export type FloatingSize = { width: number; height: number };
export type FloatingViewport = { width: number; height: number };
export type FloatingPlacement = "bottom-start" | "bottom-end" | "top-start" | "top-end";

type FloatingPositionOptions = {
    placement?: FloatingPlacement;
    gap?: number;
    margin?: number;
};

const clamp = (value: number, minimum: number, maximum: number) =>
    Math.max(minimum, Math.min(value, Math.max(minimum, maximum)));

/**
 * Keeps a viewport-level popover visible while preserving its relationship to
 * its trigger. It flips vertically before clamping when there is room.
 */
export function floatingPosition(
    anchor: FloatingRect,
    size: FloatingSize,
    viewport: FloatingViewport,
    { placement = "bottom-start", gap = 8, margin = 8 }: FloatingPositionOptions = {},
) {
    const alignEnd = placement.endsWith("end");
    const preferTop = placement.startsWith("top");
    const bottom = anchor.bottom + gap;
    const top = anchor.top - size.height - gap;
    let y = preferTop ? top : bottom;

    if (!preferTop && bottom + size.height > viewport.height - margin && top >= margin)
        y = top;
    if (preferTop && top < margin && bottom + size.height <= viewport.height - margin)
        y = bottom;

    const x = alignEnd ? anchor.right - size.width : anchor.left;
    return {
        left: Math.round(clamp(x, margin, viewport.width - size.width - margin)),
        top: Math.round(clamp(y, margin, viewport.height - size.height - margin)),
    };
}

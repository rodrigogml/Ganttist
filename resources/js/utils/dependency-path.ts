export type DependencyPort = "start" | "finish";

export type DependencyPoint = { x: number; y: number };

type DependencyPathOptions = {
    sourcePort: DependencyPort;
    targetPort: DependencyPort;
    clearance: number;
    rowHeight: number;
};

const sideFor = (port: DependencyPort) => (port === "finish" ? 1 : -1);

/**
 * Routes an orthogonal dependency while preserving the direction of both ports.
 * A close pair of opposing ports receives an approach lane around the target so
 * the final segment and its arrow never have to reverse over the target block.
 */
export function dependencyPath(
    source: DependencyPoint,
    target: DependencyPoint,
    { sourcePort, targetPort, clearance, rowHeight }: DependencyPathOptions,
): string {
    const sourceSide = sideFor(sourcePort);
    const targetSide = sideFor(targetPort);
    const sourceExitX = source.x + sourceSide * clearance;
    const targetApproachX = target.x + targetSide * clearance;
    const opposingPorts = sourceSide === -targetSide;
    const hasForwardSpace =
        sourceSide === 1
            ? sourceExitX <= targetApproachX
            : sourceExitX >= targetApproachX;

    if (!opposingPorts || hasForwardSpace) {
        const bendX = (sourceExitX + targetApproachX) / 2;
        return `M${source.x},${source.y} H${sourceExitX} H${bendX} V${target.y} H${targetApproachX} H${target.x}`;
    }

    // A barra tem 26 px de altura; a faixa fica 3 px antes de sua borda.
    const verticalClearance = Math.max(2, Math.min(16, rowHeight / 2 - 2));
    const targetApproachY =
        target.y + (target.y >= source.y ? -verticalClearance : verticalClearance);

    return `M${source.x},${source.y} H${sourceExitX} V${targetApproachY} H${targetApproachX} V${target.y} H${target.x}`;
}

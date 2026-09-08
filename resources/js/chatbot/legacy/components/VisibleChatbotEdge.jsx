import React from 'react';
import { BaseEdge, EdgeLabelRenderer, getSmoothStepPath, Position } from 'reactflow';

export default function VisibleChatbotEdge({
    id,
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition = Position.Right,
    targetPosition = Position.Top,
    style = {},
    data,
    markerEnd,
    markerStart,
    selected,
}) {
    const sx = Number.isFinite(sourceX) ? sourceX : 0;
    const sy = Number.isFinite(sourceY) ? sourceY : 0;
    const tx = Number.isFinite(targetX) ? targetX : sx;
    const ty = Number.isFinite(targetY) ? targetY : sy;

    const [edgePath, labelX, labelY] = getSmoothStepPath({
        sourceX: sx,
        sourceY: sy,
        sourcePosition: sourcePosition || Position.Right,
        targetX: tx,
        targetY: ty,
        targetPosition: targetPosition || Position.Top,
        borderRadius: 8,
    });

    if (!edgePath) {
        return null;
    }

    const strokeColor = style?.stroke || (selected ? '#15803d' : '#22c55e');
    const strokeWidth = style?.strokeWidth || (selected ? 3.5 : 2.5);

    const edgeStyle = {
        stroke: strokeColor,
        strokeWidth: strokeWidth,
        strokeLinecap: 'round',
        strokeLinejoin: 'round',
        ...style,
    };

    const labelText = data?.label || data?.replyText || (typeof data === 'string' ? data : null);

    return (
        <>
            <BaseEdge
                id={id}
                path={edgePath}
                markerEnd={markerEnd}
                markerStart={markerStart}
                style={edgeStyle}
            />
            {labelText && labelText !== 'Default Connection' && labelText !== 'Default' && (
                <EdgeLabelRenderer>
                    <div
                        style={{
                            position: 'absolute',
                            transform: `translate(-50%, -50%) translate(${labelX}px,${labelY}px)`,
                            background: '#ffffff',
                            padding: '2px 8px',
                            borderRadius: '6px',
                            fontSize: '11px',
                            fontWeight: 500,
                            color: '#15803d',
                            border: `1px solid ${selected ? '#15803d' : '#bbf7d0'}`,
                            boxShadow: '0 1px 4px rgba(0,0,0,0.08)',
                            pointerEvents: 'all',
                            userSelect: 'none',
                            zIndex: 10,
                        }}
                        className="nodrag nopan"
                    >
                        {labelText}
                    </div>
                </EdgeLabelRenderer>
            )}
        </>
    );
}


import React, { CSSProperties, ReactNode } from 'react';

import { ColumnsContainerProps } from './ColumnsContainerPropsSchema';

type ColumnsContainerBlockProps = {
  style: ColumnsContainerProps['style'];
  props: Omit<NonNullable<ColumnsContainerProps['props']>, 'columns'> | null | undefined;
  columns: ReactNode[];
};

function getPadding(padding: NonNullable<ColumnsContainerProps['style']>['padding']) {
  return padding
    ? `${padding.top}px ${padding.right}px ${padding.bottom}px ${padding.left}px`
    : undefined;
}

export default function ColumnsContainer({ style, props, columns }: ColumnsContainerBlockProps) {
  const columnsCount = props?.columnsCount ?? 2;
  const columnsGap = props?.columnsGap ?? 0;
  const contentAlignment = props?.contentAlignment ?? 'middle';
  const fixedWidths = props?.fixedWidths ?? [];
  const wrapperStyle: CSSProperties = {
    backgroundColor: style?.backgroundColor ?? undefined,
    padding: getPadding(style?.padding),
  };

  return (
    <div style={wrapperStyle}>
      <table
        align="center"
        width="100%"
        cellPadding="0"
        cellSpacing="0"
        border={0}
        role="presentation"
        style={{ tableLayout: 'fixed', borderCollapse: 'collapse' }}
      >
        <tbody style={{ width: '100%' }}>
          <tr style={{ width: '100%' }}>
            {Array.from({ length: columnsCount }, (_, columnIndex) => (
              <td
                key={columnIndex}
                width={fixedWidths[columnIndex] ?? undefined}
                valign={contentAlignment}
                style={{
                  boxSizing: 'border-box',
                  verticalAlign: contentAlignment,
                  paddingLeft: columnIndex === 0 ? 0 : columnsGap / 2,
                  paddingRight: columnIndex === columnsCount - 1 ? 0 : columnsGap / 2,
                  width: fixedWidths[columnIndex] ?? undefined,
                }}
              >
                {columns[columnIndex] ?? null}
              </td>
            ))}
          </tr>
        </tbody>
      </table>
    </div>
  );
}

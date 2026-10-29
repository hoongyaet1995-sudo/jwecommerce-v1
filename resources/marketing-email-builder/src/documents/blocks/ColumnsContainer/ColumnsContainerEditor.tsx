import React from 'react';

import { useCurrentBlockId } from '../../editor/EditorBlock';
import { setDocument, setSelectedBlockId } from '../../editor/EditorContext';
import EditorChildrenIds, { EditorChildrenChange } from '../helpers/EditorChildrenIds';

import ColumnsContainer from './ColumnsContainer';
import ColumnsContainerPropsSchema, { ColumnsContainerProps } from './ColumnsContainerPropsSchema';

const EMPTY_COLUMNS = [
  { childrenIds: [] },
  { childrenIds: [] },
  { childrenIds: [] },
  { childrenIds: [] },
];

export default function ColumnsContainerEditor({ style, props }: ColumnsContainerProps) {
  const currentBlockId = useCurrentBlockId();

  const { columns, ...restProps } = props ?? {};
  const columnsValue = [...(columns ?? EMPTY_COLUMNS)];

  while (columnsValue.length < 4) {
    columnsValue.push({ childrenIds: [] });
  }

  const updateColumn = (columnIndex: 0 | 1 | 2 | 3, { block, blockId, childrenIds }: EditorChildrenChange) => {
    const nColumns = [...columnsValue];
    nColumns[columnIndex] = { childrenIds };
    setDocument({
      [blockId]: block,
      [currentBlockId]: {
        type: 'ColumnsContainer',
        data: ColumnsContainerPropsSchema.parse({
          style,
          props: {
            ...restProps,
            columns: nColumns,
          },
        }),
      },
    });
    setSelectedBlockId(blockId);
  };

  return (
    <ColumnsContainer
      props={restProps}
      style={style}
      columns={[
        <EditorChildrenIds childrenIds={columns?.[0]?.childrenIds} onChange={(change) => updateColumn(0, change)} />,
        <EditorChildrenIds childrenIds={columns?.[1]?.childrenIds} onChange={(change) => updateColumn(1, change)} />,
        <EditorChildrenIds childrenIds={columns?.[2]?.childrenIds} onChange={(change) => updateColumn(2, change)} />,
        <EditorChildrenIds childrenIds={columns?.[3]?.childrenIds} onChange={(change) => updateColumn(3, change)} />,
      ]}
    />
  );
}

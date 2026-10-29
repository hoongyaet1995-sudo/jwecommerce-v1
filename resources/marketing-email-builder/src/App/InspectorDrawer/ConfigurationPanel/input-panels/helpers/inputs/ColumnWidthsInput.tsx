import React, { useState } from 'react';

import { Box } from '@mui/material';

import TextDimensionInput from './TextDimensionInput';

type TWidthValue = number | null | undefined;
type FixedWidths = TWidthValue[];
type ColumnsLayoutInputProps = {
  columnsCount: 2 | 3 | 4;
  defaultValue: FixedWidths | null | undefined;
  onChange: (v: FixedWidths | null | undefined) => void;
};
export default function ColumnWidthsInput({ columnsCount, defaultValue, onChange }: ColumnsLayoutInputProps) {
  const [currentValue, setCurrentValue] = useState<FixedWidths>(() => {
    if (defaultValue) {
      return [...defaultValue, null, null, null, null].slice(0, 4);
    }
    return [null, null, null, null];
  });

  const setIndexValue = (index: number, value: number | null | undefined) => {
    const nValue: FixedWidths = [...currentValue];
    nValue[index] = value;
    setCurrentValue(nValue);
    onChange(nValue);
  };

  return (
    <Box sx={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 1.5 }}>
      {Array.from({ length: columnsCount }, (_, columnIndex) => (
        <TextDimensionInput
          key={columnIndex}
          label={`Column ${columnIndex + 1}`}
          defaultValue={currentValue[columnIndex]}
          onChange={(value) => setIndexValue(columnIndex, value)}
        />
      ))}
    </Box>
  );
}

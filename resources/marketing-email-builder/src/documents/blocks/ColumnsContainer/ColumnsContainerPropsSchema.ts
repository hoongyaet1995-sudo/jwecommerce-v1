import { z } from 'zod';

import { ColumnsContainerPropsSchema as BaseColumnsContainerPropsSchema } from '@usewaypoint/block-columns-container';

const BasePropsShape = BaseColumnsContainerPropsSchema.shape.props.unwrap().unwrap().shape;
const ColumnSchema = z.object({ childrenIds: z.array(z.string()) });

const ColumnsContainerPropsSchema = z.object({
  style: BaseColumnsContainerPropsSchema.shape.style,
  props: z
    .object({
      ...BasePropsShape,
      fixedWidths: z.array(z.number().nullish()).min(3).max(4).optional().nullable(),
      columnsCount: z.union([z.literal(2), z.literal(3), z.literal(4)]).optional().nullable(),
      columns: z.array(ColumnSchema).min(3).max(4),
    })
    .optional()
    .nullable(),
});

export type ColumnsContainerProps = z.infer<typeof ColumnsContainerPropsSchema>;
export default ColumnsContainerPropsSchema;

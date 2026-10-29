import { z } from 'zod';

import { AvatarPropsSchema } from '@usewaypoint/block-avatar';
import { ButtonPropsSchema } from '@usewaypoint/block-button';
import { DividerPropsSchema } from '@usewaypoint/block-divider';
import { HeadingPropsSchema } from '@usewaypoint/block-heading';
import { HtmlPropsSchema } from '@usewaypoint/block-html';
import { ImagePropsSchema } from '@usewaypoint/block-image';
import { SpacerPropsSchema } from '@usewaypoint/block-spacer';
import { TextPropsSchema } from '@usewaypoint/block-text';

import ColumnsContainerPropsSchema from '../blocks/ColumnsContainer/ColumnsContainerPropsSchema';
import ContainerPropsSchema from '../blocks/Container/ContainerPropsSchema';
import EmailLayoutPropsSchema from '../blocks/EmailLayout/EmailLayoutPropsSchema';

export const EditorBlockSchema = z.discriminatedUnion('type', [
  z.object({ type: z.literal('Avatar'), data: AvatarPropsSchema }),
  z.object({ type: z.literal('Button'), data: ButtonPropsSchema }),
  z.object({ type: z.literal('ColumnsContainer'), data: ColumnsContainerPropsSchema }),
  z.object({ type: z.literal('Container'), data: ContainerPropsSchema }),
  z.object({ type: z.literal('Divider'), data: DividerPropsSchema }),
  z.object({ type: z.literal('EmailLayout'), data: EmailLayoutPropsSchema }),
  z.object({ type: z.literal('Heading'), data: HeadingPropsSchema }),
  z.object({ type: z.literal('Html'), data: HtmlPropsSchema }),
  z.object({ type: z.literal('Image'), data: ImagePropsSchema }),
  z.object({ type: z.literal('Spacer'), data: SpacerPropsSchema }),
  z.object({ type: z.literal('Text'), data: TextPropsSchema }),
]);

function normalizeLegacySpacerData(value: unknown) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    return value;
  }

  return Object.fromEntries(Object.entries(value).map(([blockId, block]) => {
    if (block
      && typeof block === 'object'
      && !Array.isArray(block)
      && (block as { type?: unknown }).type === 'Spacer'
      && Array.isArray((block as { data?: unknown }).data)
      && ((block as { data: unknown[] }).data).length === 0) {
      return [blockId, { ...block, data: {} }];
    }

    return [blockId, block];
  }));
}

export const EditorConfigurationSchema = z.preprocess(
  normalizeLegacySpacerData,
  z.record(z.string(), EditorBlockSchema)
);

export type TEditorBlock = z.infer<typeof EditorBlockSchema>;
export type TEditorConfiguration = Record<string, TEditorBlock>;

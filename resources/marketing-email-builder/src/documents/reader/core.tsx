import React, { createContext, useContext } from 'react';
import { renderToStaticMarkup as renderReactToStaticMarkup } from 'react-dom/server';
import { z } from 'zod';

import { Avatar, AvatarPropsSchema } from '@usewaypoint/block-avatar';
import { Button, ButtonPropsSchema } from '@usewaypoint/block-button';
import { Container as BaseContainer } from '@usewaypoint/block-container';
import { Divider, DividerPropsSchema } from '@usewaypoint/block-divider';
import { Heading, HeadingPropsSchema } from '@usewaypoint/block-heading';
import { Html, HtmlPropsSchema } from '@usewaypoint/block-html';
import { Image, ImagePropsSchema } from '@usewaypoint/block-image';
import { Spacer, SpacerPropsSchema } from '@usewaypoint/block-spacer';
import { Text, TextPropsSchema } from '@usewaypoint/block-text';
import {
  buildBlockComponent,
  buildBlockConfigurationDictionary,
  buildBlockConfigurationSchema,
} from '@usewaypoint/document-core';

import ColumnsContainer from '../blocks/ColumnsContainer/ColumnsContainer';
import ColumnsContainerPropsSchema, {
  ColumnsContainerProps,
} from '../blocks/ColumnsContainer/ColumnsContainerPropsSchema';
import ContainerPropsSchema, { ContainerProps } from '../blocks/Container/ContainerPropsSchema';
import EmailLayoutPropsSchema, { EmailLayoutProps } from '../blocks/EmailLayout/EmailLayoutPropsSchema';
import { FONT_FAMILIES } from '../blocks/helpers/fontFamily';
import type { TEditorConfiguration } from '../editor/schema';

const ReaderContext = createContext<TEditorConfiguration>({});

function useReaderDocument() {
  return useContext(ReaderContext);
}

function ColumnsContainerReader({ style, props }: ColumnsContainerProps) {
  const { columns, ...containerProps } = props ?? {};
  const renderedColumns = (columns ?? []).map((column) => (
    column.childrenIds.map((childId) => <ReaderBlock key={childId} id={childId} />)
  ));

  return <ColumnsContainer style={style} props={containerProps} columns={renderedColumns} />;
}

function ContainerReader({ style, props }: ContainerProps) {
  const childrenIds = props?.childrenIds ?? [];

  return (
    <BaseContainer style={style}>
      {childrenIds.map((childId) => <ReaderBlock key={childId} id={childId} />)}
    </BaseContainer>
  );
}

function getFontFamily(fontFamily: EmailLayoutProps['fontFamily']) {
  return FONT_FAMILIES.find((option) => option.key === (fontFamily ?? 'MODERN_SANS'))?.value
    ?? FONT_FAMILIES[0].value;
}

function EmailLayoutReader(props: EmailLayoutProps) {
  const childrenIds = props.childrenIds ?? [];
  const canvasWidth = props.canvasWidth ?? 600;
  const backdropPadding = props.backdropPadding ?? 16;
  const border = props.borderColor ? `1px solid ${props.borderColor}` : undefined;
  const backdropColor = props.backdropColor ?? '#F5F5F5';
  const canvasColor = props.canvasColor ?? '#FFFFFF';

  return (
    <table
      width="100%"
      style={{
        backgroundColor: backdropColor,
        borderCollapse: 'collapse',
        color: props.textColor ?? '#262626',
        fontFamily: getFontFamily(props.fontFamily),
        fontSize: '16px',
        fontWeight: '400',
        letterSpacing: '0.15008px',
        lineHeight: '1.5',
        margin: '0',
        width: '100%',
      }}
      role="presentation"
      cellSpacing="0"
      cellPadding="0"
      border={0}
      bgcolor={backdropColor}
    >
      <tbody>
        <tr>
          <td align="center" style={{ padding: `32px ${backdropPadding}px` }}>
            <table
              align="center"
              width={canvasWidth}
              style={{
                margin: '0 auto',
                width: '100%',
                maxWidth: `${canvasWidth}px`,
                backgroundColor: canvasColor,
                borderRadius: props.borderRadius ?? undefined,
                border,
              }}
              role="presentation"
              cellSpacing="0"
              cellPadding="0"
              border={0}
              bgcolor={canvasColor}
            >
              <tbody>
                <tr style={{ width: '100%' }}>
                  <td style={{ padding: '0' }}>
                    {childrenIds.map((childId) => <ReaderBlock key={childId} id={childId} />)}
                  </td>
                </tr>
              </tbody>
            </table>
          </td>
        </tr>
      </tbody>
    </table>
  );
}

const READER_DICTIONARY = buildBlockConfigurationDictionary({
  Avatar: { schema: AvatarPropsSchema, Component: Avatar },
  Button: { schema: ButtonPropsSchema, Component: Button },
  ColumnsContainer: { schema: ColumnsContainerPropsSchema, Component: ColumnsContainerReader },
  Container: { schema: ContainerPropsSchema, Component: ContainerReader },
  Divider: { schema: DividerPropsSchema, Component: Divider },
  EmailLayout: { schema: EmailLayoutPropsSchema, Component: EmailLayoutReader },
  Heading: { schema: HeadingPropsSchema, Component: Heading },
  Html: { schema: HtmlPropsSchema, Component: Html },
  Image: { schema: ImagePropsSchema, Component: Image },
  Spacer: { schema: SpacerPropsSchema, Component: Spacer },
  Text: { schema: TextPropsSchema, Component: Text },
});

export const ReaderBlockSchema = buildBlockConfigurationSchema(READER_DICTIONARY);
export const ReaderDocumentSchema = z.record(z.string(), ReaderBlockSchema);
const BaseReaderBlock = buildBlockComponent(READER_DICTIONARY);

export function ReaderBlock({ id }: { id: string }) {
  const document = useReaderDocument();
  return <BaseReaderBlock {...document[id]} />;
}

export function Reader({ document, rootBlockId }: { document: TEditorConfiguration; rootBlockId: string }) {
  return (
    <ReaderContext.Provider value={document}>
      <ReaderBlock id={rootBlockId} />
    </ReaderContext.Provider>
  );
}

export function renderToStaticMarkup(document: TEditorConfiguration, { rootBlockId }: { rootBlockId: string }) {
  return '<!DOCTYPE html>' + renderReactToStaticMarkup(
    <html>
      <body>
        <Reader document={document} rootBlockId={rootBlockId} />
      </body>
    </html>
  );
}

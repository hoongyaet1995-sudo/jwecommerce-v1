import React, { useState } from 'react';
import { ZodError } from 'zod';

import { HtmlProps, HtmlPropsSchema } from '@usewaypoint/block-html';

import { parseSocialIconsHtml } from '../../../../socialIcons';

import BaseSidebarPanel from './helpers/BaseSidebarPanel';
import TextInput from './helpers/inputs/TextInput';
import MultiStylePropertyPanel from './helpers/style-inputs/MultiStylePropertyPanel';
import SocialIconsSidebarPanel from './SocialIconsSidebarPanel';

type HtmlSidebarPanelProps = {
  data: HtmlProps;
  setData: (v: HtmlProps) => void;
};
export default function HtmlSidebarPanel({ data, setData }: HtmlSidebarPanelProps) {
  const [, setErrors] = useState<ZodError | null>(null);

  const updateData = (d: unknown) => {
    const res = HtmlPropsSchema.safeParse(d);
    if (res.success) {
      setData(res.data);
      setErrors(null);
    } else {
      setErrors(res.error);
    }
  };

  const socialIconsConfiguration = parseSocialIconsHtml(data.props?.contents);

  if (socialIconsConfiguration) {
    return (
      <SocialIconsSidebarPanel
        data={data}
        configuration={socialIconsConfiguration}
        setData={updateData}
      />
    );
  }

  return (
    <BaseSidebarPanel title="Html block">
      <TextInput
        label="Content"
        rows={5}
        defaultValue={data.props?.contents ?? ''}
        onChange={(contents) => updateData({ ...data, props: { ...data.props, contents } })}
      />
      <MultiStylePropertyPanel
        names={['color', 'backgroundColor', 'fontFamily', 'fontSize', 'textAlign', 'padding']}
        value={data.style}
        onChange={(style) => updateData({ ...data, style })}
      />
    </BaseSidebarPanel>
  );
}

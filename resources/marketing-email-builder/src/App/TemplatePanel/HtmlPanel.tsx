import React, { useMemo } from 'react';

import { useDocument } from '../../documents/editor/EditorContext';
import { renderToStaticMarkup } from '../../documents/reader/core';

import HighlightedCodePanel from './helper/HighlightedCodePanel';

export default function HtmlPanel() {
  const document = useDocument();
  const code = useMemo(() => renderToStaticMarkup(document, { rootBlockId: 'root' }), [document]);
  return <HighlightedCodePanel type="html" value={code} />;
}

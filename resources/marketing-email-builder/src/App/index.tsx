import React from 'react';

import { Box } from '@mui/material';

import InspectorDrawer from './InspectorDrawer';
import TemplatePanel from './TemplatePanel';

export default function App() {
  return (
    <Box sx={{ display: 'flex', width: '100%', height: '100%', minWidth: 720 }}>
      <Box sx={{ flex: 1, minWidth: 400 }}>
        <TemplatePanel />
      </Box>
      <InspectorDrawer />
    </Box>
  );
}

import React from 'react';

import { PhotoSizeSelectSmallOutlined, SpaceBarOutlined } from '@mui/icons-material';
import { Divider, FormControlLabel, Stack, Switch, ToggleButton, Typography } from '@mui/material';
import { HtmlProps } from '@usewaypoint/block-html';

import {
  buildSocialIconsHtml,
  createSocialProfileUrl,
  SOCIAL_PLATFORMS,
  SocialIconsConfiguration,
} from '../../../../socialIcons';

import BaseSidebarPanel from './helpers/BaseSidebarPanel';
import { NullableColorInput } from './helpers/inputs/ColorInput';
import RadioGroupInput from './helpers/inputs/RadioGroupInput';
import SliderInput from './helpers/inputs/SliderInput';
import TextInput from './helpers/inputs/TextInput';
import MultiStylePropertyPanel from './helpers/style-inputs/MultiStylePropertyPanel';

type SocialIconsSidebarPanelProps = {
  data: HtmlProps;
  configuration: SocialIconsConfiguration;
  setData: (v: HtmlProps) => void;
};

export default function SocialIconsSidebarPanel({
  data,
  configuration,
  setData,
}: SocialIconsSidebarPanelProps) {
  const updateConfiguration = (nextConfiguration: SocialIconsConfiguration) => {
    setData({
      ...data,
      props: {
        ...data.props,
        contents: buildSocialIconsHtml(nextConfiguration),
      },
    });
  };

  return (
    <BaseSidebarPanel title="Social icons">
      <Typography variant="body2" color="text.secondary">
        Enable the networks you need, then enter a username or full profile URL. White icon-only artwork works best on a coloured block background.
      </Typography>

      <RadioGroupInput
        label="Icon style"
        defaultValue={configuration.iconStyle}
        onChange={(iconStyle) => updateConfiguration({
          ...configuration,
          iconStyle: iconStyle === 'dark' || iconStyle === 'white' ? iconStyle : 'brand',
        })}
      >
        <ToggleButton value="brand">Brand</ToggleButton>
        <ToggleButton value="dark">Dark</ToggleButton>
        <ToggleButton value="white">White</ToggleButton>
      </RadioGroupInput>

      <RadioGroupInput
        label="Icon shape"
        defaultValue={configuration.iconType}
        onChange={(iconType) => updateConfiguration({
          ...configuration,
          iconType: iconType as SocialIconsConfiguration['iconType'],
        })}
      >
        <ToggleButton value="square">Square</ToggleButton>
        <ToggleButton value="circle">Rounded</ToggleButton>
        <ToggleButton value="plain">Icon only</ToggleButton>
      </RadioGroupInput>

      {configuration.iconType === 'plain' ? (
        <NullableColorInput
          label="Custom icon color"
          defaultValue={configuration.customColor}
          onChange={(customColor) => updateConfiguration({ ...configuration, customColor })}
        />
      ) : null}

      <RadioGroupInput
        label="Alignment"
        defaultValue={configuration.alignment}
        onChange={(alignment) => updateConfiguration({
          ...configuration,
          alignment: alignment as SocialIconsConfiguration['alignment'],
        })}
      >
        <ToggleButton value="left">Left</ToggleButton>
        <ToggleButton value="center">Centre</ToggleButton>
        <ToggleButton value="right">Right</ToggleButton>
      </RadioGroupInput>

      <SliderInput
        label="Icon size"
        iconLabel={<PhotoSizeSelectSmallOutlined fontSize="small" />}
        units="px"
        min={20}
        max={48}
        step={4}
        marks
        defaultValue={configuration.iconSize}
        onChange={(iconSize) => updateConfiguration({ ...configuration, iconSize })}
      />

      <SliderInput
        label="Icon spacing"
        iconLabel={<SpaceBarOutlined fontSize="small" />}
        units="px"
        min={0}
        max={24}
        step={2}
        marks
        defaultValue={configuration.spacing}
        onChange={(spacing) => updateConfiguration({ ...configuration, spacing })}
      />

      <Stack spacing={3} divider={<Divider flexItem />}>
        {SOCIAL_PLATFORMS.map((platform) => {
          const platformConfiguration = configuration.platforms[platform.key];
          const profileUrl = createSocialProfileUrl(platform.key, platformConfiguration.value);

          return (
            <Stack key={platform.key} spacing={1.5}>
              <FormControlLabel
                label={platform.label}
                control={(
                  <Switch
                    checked={platformConfiguration.enabled}
                    onChange={(_, enabled) => updateConfiguration({
                      ...configuration,
                      platforms: {
                        ...configuration.platforms,
                        [platform.key]: { ...platformConfiguration, enabled },
                      },
                    })}
                  />
                )}
              />
              <TextInput
                label="Username or profile URL"
                placeholder={platform.usernameHint}
                helperText={profileUrl === '' ? 'Add a value to make this icon clickable.' : profileUrl}
                defaultValue={platformConfiguration.value}
                onChange={(value) => updateConfiguration({
                  ...configuration,
                  platforms: {
                    ...configuration.platforms,
                    [platform.key]: { ...platformConfiguration, value },
                  },
                })}
              />
            </Stack>
          );
        })}
      </Stack>

      <MultiStylePropertyPanel
        names={['backgroundColor', 'padding']}
        value={data.style}
        onChange={(style) => setData({ ...data, style })}
      />
    </BaseSidebarPanel>
  );
}

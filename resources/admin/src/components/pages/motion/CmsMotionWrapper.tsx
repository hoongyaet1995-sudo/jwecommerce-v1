import React, { createContext, useContext } from 'react';
import { motion, useReducedMotion, type Variants } from 'motion/react';
import { SectionMotionPreset } from '../../../types';

interface SectionMotionContextType {
  preset: SectionMotionPreset;
  isMotionEnabled: boolean;
}

const SectionMotionContext = createContext<SectionMotionContextType>({
  preset: 'subtle-lift',
  isMotionEnabled: true
});

export const useSectionMotion = () => useContext(SectionMotionContext);

// Motion Presets Definition
export const MOTION_PRESET_DETAILS: Record<
  SectionMotionPreset,
  { label: string; desc: string; iconTag: string }
> = {
  'subtle-lift': {
    label: 'Subtle Lift (Default)',
    desc: 'Gentle 16px upward float + smooth opacity fade (Apple-style natural curve)',
    iconTag: 'Lift'
  },
  'fade-in': {
    label: 'Cinematic Fade',
    desc: 'Pure opacity easing without displacement, ideal for editorial & luxury media',
    iconTag: 'Fade'
  },
  'smooth-zoom': {
    label: 'Smooth Zoom',
    desc: 'Delicate focal zoom-in (0.96 to 1.0) with subtle elevation',
    iconTag: 'Zoom'
  },
  'dynamic-pop': {
    label: 'Dynamic Pop',
    desc: 'Energetic spring bounce, perfect for flash drops & hero campaigns',
    iconTag: 'Pop'
  },
  'none': {
    label: 'None (Static)',
    desc: 'Instant static rendering without CSS or JS motion effects',
    iconTag: 'Off'
  }
};

// Container Variants for Orchestrated Cascade Stagger
const getContainerVariants = (preset: SectionMotionPreset): Variants => {
  if (preset === 'none') {
    return {
      hidden: {},
      visible: {}
    };
  }

  const stagger = preset === 'fade-in' ? 0.09 : preset === 'dynamic-pop' ? 0.08 : 0.08;

  switch (preset) {
    case 'fade-in':
      return {
        hidden: { opacity: 0 },
        visible: {
          opacity: 1,
          transition: {
            duration: 0.5,
            ease: 'easeOut',
            staggerChildren: stagger,
            delayChildren: 0.04
          }
        }
      };
    case 'smooth-zoom':
      return {
        hidden: { opacity: 0, scale: 0.98 },
        visible: {
          opacity: 1,
          scale: 1,
          transition: {
            duration: 0.52,
            ease: [0.16, 1, 0.3, 1],
            staggerChildren: stagger,
            delayChildren: 0.04
          }
        }
      };
    case 'dynamic-pop':
      return {
        hidden: { opacity: 0, y: 14, scale: 0.98 },
        visible: {
          opacity: 1,
          y: 0,
          scale: 1,
          transition: {
            type: 'spring',
            stiffness: 280,
            damping: 24,
            staggerChildren: stagger,
            delayChildren: 0.04
          }
        }
      };
    case 'subtle-lift':
    default:
      return {
        hidden: { opacity: 0, y: 16 },
        visible: {
          opacity: 1,
          y: 0,
          transition: {
            duration: 0.5,
            ease: [0.22, 1, 0.36, 1],
            staggerChildren: stagger,
            delayChildren: 0.04
          }
        }
      };
  }
};

// Item Variants mapping based on preset
const getItemVariants = (preset: SectionMotionPreset): Variants => {
  switch (preset) {
    case 'fade-in':
      return {
        hidden: { opacity: 0 },
        visible: {
          opacity: 1,
          transition: { duration: 0.55, ease: 'easeOut' }
        }
      };
    case 'smooth-zoom':
      return {
        hidden: { opacity: 0, scale: 0.96, y: 8 },
        visible: {
          opacity: 1,
          scale: 1,
          y: 0,
          transition: { duration: 0.52, ease: [0.16, 1, 0.3, 1] }
        }
      };
    case 'dynamic-pop':
      return {
        hidden: { opacity: 0, scale: 0.94, y: 20 },
        visible: {
          opacity: 1,
          scale: 1,
          y: 0,
          transition: {
            type: 'spring',
            stiffness: 320,
            damping: 22
          }
        }
      };
    case 'none':
      return {
        hidden: {},
        visible: {}
      };
    case 'subtle-lift':
    default:
      return {
        hidden: { opacity: 0, y: 16 },
        visible: {
          opacity: 1,
          y: 0,
          transition: {
            duration: 0.5,
            ease: [0.22, 1, 0.36, 1]
          }
        }
      };
  }
};

// Media (Hero / Split Image) Specific Reveal Variants
const getMediaVariants = (preset: SectionMotionPreset): Variants => {
  if (preset === 'none') {
    return { hidden: {}, visible: {} };
  }
  return {
    hidden: {
      opacity: 0,
      scale: preset === 'smooth-zoom' ? 1.05 : 1.02
    },
    visible: {
      opacity: 1,
      scale: 1,
      transition: {
        duration: 0.75,
        ease: [0.22, 1, 0.36, 1]
      }
    }
  };
};

interface CmsMotionWrapperProps extends React.HTMLAttributes<HTMLDivElement> {
  preset?: SectionMotionPreset;
  replayKey?: string | number;
  isEditingMode?: boolean;
  children: React.ReactNode;
}

/**
 * Universal Section-level Motion Container
 * Handles viewport scroll-triggering, orchestrated child staggering, and editor preview re-runs
 */
export const CmsMotionWrapper: React.FC<CmsMotionWrapperProps> = ({
  preset: rawPreset = 'subtle-lift',
  replayKey,
  isEditingMode = false,
  className = '',
  style,
  children,
  ...rest
}) => {
  const preset: SectionMotionPreset = (rawPreset as SectionMotionPreset) || 'subtle-lift';
  const reducedMotion = useReducedMotion();
  const isMotionEnabled = preset !== 'none' && !reducedMotion && typeof IntersectionObserver !== 'undefined';
  const containerVariants = getContainerVariants(preset);

  return (
    <SectionMotionContext.Provider value={{ preset, isMotionEnabled }}>
      {isMotionEnabled ? (
        <motion.div
          key={replayKey}
          initial="hidden"
          whileInView="visible"
          viewport={{ once: true, amount: 0.05 }}
          variants={containerVariants}
          className={className}
          style={style}
          {...rest}
        >
          {children}
        </motion.div>
      ) : (
        <div className={className} style={style} {...rest}>
          {children}
        </div>
      )}
    </SectionMotionContext.Provider>
  );
};

interface MotionItemProps extends React.HTMLAttributes<HTMLDivElement> {
  className?: string;
  delay?: number;
  children: React.ReactNode;
}

/**
 * Atomic Element Motion Item
 * Eyebrow tags, titles, descriptions, badges that naturally stagger
 */
export const MotionItem: React.FC<MotionItemProps> = ({
  className = '',
  delay,
  children,
  style,
  ...rest
}) => {
  const { preset, isMotionEnabled } = useSectionMotion();

  if (!isMotionEnabled) {
    return (
      <div className={className} style={style} {...rest}>
        {children}
      </div>
    );
  }

  const itemVariants = getItemVariants(preset);

  return (
    <motion.div
      variants={itemVariants}
      transition={delay ? { delay } : undefined}
      className={className}
      style={style}
      {...rest}
    >
      {children}
    </motion.div>
  );
};

/**
 * Atomic Media Motion Item
 * For Section background hero photos, split imagery, or video thumbnails
 */
export const MotionMedia: React.FC<MotionItemProps> = ({
  className = '',
  children,
  style,
  ...rest
}) => {
  const { preset, isMotionEnabled } = useSectionMotion();

  if (!isMotionEnabled) {
    return (
      <div className={className} style={style} {...rest}>
        {children}
      </div>
    );
  }

  const mediaVariants = getMediaVariants(preset);

  return (
    <motion.div
      variants={mediaVariants}
      className={className}
      style={style}
      {...rest}
    >
      {children}
    </motion.div>
  );
};

/**
 * Atomic Button Motion Item
 * Inherits stagger entrance + subtle interactive spring hover/tap physics
 */
export const MotionButton: React.FC<MotionItemProps> = ({
  className = '',
  children,
  style,
  ...rest
}) => {
  const { preset, isMotionEnabled } = useSectionMotion();

  if (!isMotionEnabled) {
    return (
      <div className={className} style={style} {...rest}>
        {children}
      </div>
    );
  }

  const itemVariants = getItemVariants(preset);

  return (
    <motion.div
      variants={itemVariants}
      whileHover={{ scale: 1.025 }}
      whileTap={{ scale: 0.975 }}
      transition={{ type: 'spring', stiffness: 400, damping: 25 }}
      className={`inline-block ${className}`}
      style={style}
      {...rest}
    >
      {children}
    </motion.div>
  );
};

/**
 * Atomic Card Motion Item
 * For product cards, category tiles, or feature blocks with stagger + subtle hover elevation
 */
export const MotionCard: React.FC<MotionItemProps> = ({
  className = '',
  delay,
  children,
  style,
  ...rest
}) => {
  const { preset, isMotionEnabled } = useSectionMotion();

  if (!isMotionEnabled) {
    return (
      <div className={className} style={style} {...rest}>
        {children}
      </div>
    );
  }

  const itemVariants = getItemVariants(preset);

  return (
    <motion.div
      variants={itemVariants}
      transition={delay ? { delay } : undefined}
      whileHover={{ y: -4, transition: { duration: 0.2 } }}
      className={className}
      style={style}
      {...rest}
    >
      {children}
    </motion.div>
  );
};

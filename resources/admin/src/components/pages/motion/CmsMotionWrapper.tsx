import React, { createContext, useContext, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { motion, useReducedMotion, type Variants } from 'motion/react';
import { SectionMotionPreset } from '../../../types';

interface SectionMotionContextType {
  preset: SectionMotionPreset;
  isMotionEnabled: boolean;
  pace: 'smooth' | 'relaxed';
  replayKey?: string | number;
}

const SectionMotionContext = createContext<SectionMotionContextType>({
  preset: 'subtle-lift',
  isMotionEnabled: true,
  pace: 'smooth'
});

export const useSectionMotion = () => useContext(SectionMotionContext);

// Motion Presets Definition
export const MOTION_PRESET_DETAILS: Record<
  SectionMotionPreset,
  { label: string; desc: string; iconTag: string }
> = {
  'subtle-lift': {
    label: 'Subtle Lift (Default)',
    desc: 'Reveals after entering the viewport with a gentle 35px lift and smooth fade',
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
const getContainerVariants = (preset: SectionMotionPreset, duration=.8): Variants => {
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
            duration,
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
            duration,
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
        hidden: { opacity: 0, y: 35 },
        visible: {
          opacity: 1,
          y: 0,
          transition: {
            duration,
            ease: [0.2, 0.75, 0.2, 1],
            opacity: { duration, ease: [0.25, 0.1, 0.25, 1] },
            y: { duration, ease: [0.2, 0.75, 0.2, 1] },
            staggerChildren: stagger,
            delayChildren: 0.04
          }
        }
      };
  }
};

// Item Variants mapping based on preset
const getItemVariants = (preset: SectionMotionPreset, duration=.8): Variants => {
  switch (preset) {
    case 'fade-in':
      return {
        hidden: { opacity: 0 },
        visible: {
          opacity: 1,
          transition: { duration, ease: 'easeOut' }
        }
      };
    case 'smooth-zoom':
      return {
        hidden: { opacity: 0, scale: 0.96, y: 8 },
        visible: {
          opacity: 1,
          scale: 1,
          y: 0,
          transition: { duration, ease: [0.16, 1, 0.3, 1] }
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
        hidden: { opacity: 0, y: 35 },
        visible: {
          opacity: 1,
          y: 0,
          transition: {
            duration,
            ease: [0.2, 0.75, 0.2, 1],
            opacity: { duration, ease: [0.25, 0.1, 0.25, 1] },
            y: { duration, ease: [0.2, 0.75, 0.2, 1] }
          }
        }
      };
  }
};

// Media (Hero / Split Image) Specific Reveal Variants
const getMediaVariants = (preset: SectionMotionPreset, duration=.8): Variants => {
  if (preset === 'none') {
    return { hidden: {}, visible: {} };
  }
  if (preset === 'subtle-lift') return getItemVariants(preset, duration);
  return {
    hidden: {
      opacity: 0,
      scale: preset === 'smooth-zoom' ? 1.05 : 1.02
    },
    visible: {
      opacity: 1,
      scale: 1,
      transition: {
        duration,
        ease: [0.22, 1, 0.36, 1]
      }
    }
  };
};

// A reveal region containing independently revealed content must not add a
// second 35px displacement. Keep its fade for unwrapped copy in mixed layouts.
function leafLiftVariants(variants: Variants, preset: SectionMotionPreset, nested: boolean): Variants {
  if (preset !== 'subtle-lift' || !nested) return variants;
  return {...variants, hidden: {...(variants.hidden as object), y: 0}};
}

// Observe the actual reveal target. A tall section cannot reach 12% visibility,
// so cap the required visible area at 12% of the usable viewport height.
export const revealThreshold = (height: number, viewportHeight: number) =>
  Math.min(.12, Math.max(1, viewportHeight - 35) * .12 / Math.max(1, height));

function useElementReveal(enabled: boolean, replayKey?: string | number) {
  const nodeRef = useRef<HTMLDivElement>(null);
  const [visible, setVisible] = useState(false);
  const [hasNestedTargets, setHasNestedTargets] = useState(false);
  useLayoutEffect(() => {
    setHasNestedTargets(Boolean(nodeRef.current?.querySelector('[data-cms-reveal-state]')));
  });
  useEffect(() => {
    setVisible(false);
    const node = nodeRef.current;
    if (!node || !enabled) return;
    let observer: IntersectionObserver;
    const observe = () => {
      observer?.disconnect();
      const threshold = revealThreshold(node.getBoundingClientRect().height, window.innerHeight);
      observer = new IntersectionObserver(entries => {
        if (entries.some(entry => entry.isIntersecting && entry.intersectionRatio >= threshold)) {
          setVisible(true);
          observer.disconnect();
          resize?.disconnect();
        }
      }, {threshold, rootMargin: '0px 0px -35px 0px'});
      observer.observe(node);
    };
    const resize = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(observe);
    observe();
    resize?.observe(node);
    return () => {observer.disconnect(); resize?.disconnect()};
  }, [enabled, replayKey]);
  return {nodeRef, visible, hasNestedTargets};
}

// Native drag/animation handlers overlap Motion's gesture/lifecycle signatures.
type MotionSafeDivAttributes = Omit<React.HTMLAttributes<HTMLDivElement>, 'onDrag'|'onDragStart'|'onDragEnd'|'onAnimationStart'>;
interface CmsMotionWrapperProps extends MotionSafeDivAttributes {
  preset?: SectionMotionPreset;
  pace?: 'smooth' | 'relaxed';
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
  pace='smooth',
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
  const { nodeRef, visible, hasNestedTargets } = useElementReveal(isMotionEnabled, replayKey);

  const containerVariants = leafLiftVariants(getContainerVariants(preset,pace==='relaxed'?1.1:.8), preset, hasNestedTargets);

  return (
    <SectionMotionContext.Provider value={{ preset, isMotionEnabled, pace, replayKey }}>
      {isMotionEnabled ? (
        <motion.div
          key={replayKey}
          ref={nodeRef}
          initial="hidden"
          animate={visible?'visible':'hidden'}
          variants={containerVariants}
          className={className}
          data-cms-reveal-state={visible ? 'visible' : 'hidden'}
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

interface MotionItemProps extends MotionSafeDivAttributes {
  ref?: React.Ref<HTMLDivElement>;
  className?: string;
  delay?: number;
  independentReveal?: boolean;
  children: React.ReactNode;
}

/**
 * Atomic Element Motion Item
 * Eyebrow tags, titles, descriptions, badges that naturally stagger
 */
export const MotionItem: React.FC<MotionItemProps> = ({
  className = '',
  delay,
  independentReveal = false,
  children,
  style,
  ...rest
}) => {
  const { preset, isMotionEnabled, pace, replayKey } = useSectionMotion();
  const { nodeRef, visible, hasNestedTargets } = useElementReveal(isMotionEnabled, replayKey);

  if (!isMotionEnabled) {
    return (
      <div className={className} style={style} {...rest}>
        {children}
      </div>
    );
  }

  const itemVariants = leafLiftVariants(getItemVariants(preset,pace==='relaxed'?1.1:.8), preset, hasNestedTargets);

  return (
    <motion.div
      ref={nodeRef}
      initial="hidden"
      animate={visible ? 'visible' : 'hidden'}
      data-cms-reveal-state={visible ? 'visible' : 'hidden'}
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
  const { preset, isMotionEnabled, pace, replayKey } = useSectionMotion();
  const { nodeRef, visible, hasNestedTargets } = useElementReveal(isMotionEnabled, replayKey);

  if (!isMotionEnabled) {
    return (
      <div className={className} style={style} {...rest}>
        {children}
      </div>
    );
  }

  const mediaVariants = leafLiftVariants(getMediaVariants(preset,pace==='relaxed'?1.1:.8), preset, hasNestedTargets);

  return (
    <motion.div
      ref={nodeRef}
      initial="hidden"
      animate={visible ? 'visible' : 'hidden'}
      data-cms-reveal-state={visible ? 'visible' : 'hidden'}
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
  const { preset, isMotionEnabled, pace, replayKey } = useSectionMotion();
  const { nodeRef, visible, hasNestedTargets } = useElementReveal(isMotionEnabled, replayKey);

  if (!isMotionEnabled) {
    return (
      <div className={className} style={style} {...rest}>
        {children}
      </div>
    );
  }

  const itemVariants = leafLiftVariants(getItemVariants(preset,pace==='relaxed'?1.1:.8), preset, hasNestedTargets);

  return (
    <motion.div
      ref={nodeRef}
      initial="hidden"
      animate={visible ? 'visible' : 'hidden'}
      data-cms-reveal-state={visible ? 'visible' : 'hidden'}
      variants={itemVariants}
      whileHover={{ scale: 1.025, transition: { type: 'spring', stiffness: 400, damping: 25 } }}
      whileTap={{ scale: 0.975, transition: { type: 'spring', stiffness: 400, damping: 25 } }}
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
  const { preset, isMotionEnabled, pace, replayKey } = useSectionMotion();
  const { nodeRef, visible, hasNestedTargets } = useElementReveal(isMotionEnabled, replayKey);

  if (!isMotionEnabled) {
    return (
      <div className={className} style={style} {...rest}>
        {children}
      </div>
    );
  }

  const itemVariants = leafLiftVariants(getItemVariants(preset,pace==='relaxed'?1.1:.8), preset, hasNestedTargets);

  return (
    <motion.div
      ref={nodeRef}
      initial="hidden"
      animate={visible ? 'visible' : 'hidden'}
      data-cms-reveal-state={visible ? 'visible' : 'hidden'}
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

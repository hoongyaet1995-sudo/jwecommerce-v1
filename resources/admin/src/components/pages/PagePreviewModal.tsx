import React, { useState, useEffect, useRef } from 'react';
import { CmsPage } from '../../types';
import {
  X,
  Monitor,
  Tablet,
  Smartphone,
  ChevronDown,
  ChevronUp,
  Layers,
  ListOrdered,
  Home
} from 'lucide-react';

interface PagePreviewModalProps {
  isOpen: boolean;
  onClose: () => void;
  page: CmsPage | null;
  onSetHomepage?: (id: string) => Promise<void>;
}

export const PagePreviewModal: React.FC<PagePreviewModalProps> = ({
  isOpen,
  onClose,
  page,
  onSetHomepage
}) => {
  const [viewport, setViewport] = useState<'desktop' | 'tablet' | 'mobile'>('desktop');
  const [activeSectionIdx, setActiveSectionIdx] = useState(0);
  const [showSectionNavigator, setShowSectionNavigator] = useState(true);
  const [scrollProgress, setScrollProgress] = useState(0);
  const scrollContainerRef = useRef<HTMLDivElement>(null);
  const iframeRef = useRef<HTMLIFrameElement>(null);
  const detachIframeInteractionsRef = useRef<(() => void) | null>(null);
  const keydownHandlerRef = useRef<(event: KeyboardEvent) => void>(() => undefined);
  const sharedKeydownListenerRef = useRef((event: KeyboardEvent) => keydownHandlerRef.current(event));

  const visibleSections = page?.sections.filter(s => s.isVisible) || [];

  // Track scroll progress and current visible section
  const handleScroll = () => {
    const frameDocument = iframeRef.current?.contentDocument;
    const scrollElement = frameDocument?.scrollingElement;
    if (!scrollElement) return;
    const { scrollTop, scrollHeight, clientHeight } = scrollElement;
    const progress = scrollHeight > clientHeight ? (scrollTop / (scrollHeight - clientHeight)) * 100 : 0;
    setScrollProgress(progress);

    // Determine which section is currently at viewport top
    if (visibleSections.length > 0) {
      visibleSections.forEach((sec, idx) => {
        const el = frameDocument.getElementById(`section-${sec.id}`);
        if (el) {
          const rect = el.getBoundingClientRect();
          if (rect.top <= 200 && rect.bottom >= 100) {
            setActiveSectionIdx(idx);
          }
        }
      });
    }
  };

  const scrollToSection = (sectionId: string, idx: number) => {
    setActiveSectionIdx(idx);
    const target = iframeRef.current?.contentDocument?.getElementById(`section-${sectionId}`);
    if (target) {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  const handleIframeLoad = () => {
    detachIframeInteractionsRef.current?.();
    detachIframeInteractionsRef.current = null;

    const detach: Array<() => void> = [];
    try {
      const frameWindow = iframeRef.current?.contentWindow;
      if (!frameWindow) return;
      const listener = () => handleScroll();
      frameWindow.addEventListener('scroll', listener, { passive: true });
      detach.push(() => frameWindow.removeEventListener('scroll', listener));
      frameWindow.addEventListener('keydown', sharedKeydownListenerRef.current);
      detach.push(() => frameWindow.removeEventListener('keydown', sharedKeydownListenerRef.current));
      detachIframeInteractionsRef.current = () => detach.forEach(remove => remove());
      handleScroll();
    } catch {
      detach.forEach(remove => remove());
      setScrollProgress(0);
    }
  };

  const scrollToNextSection = () => {
    const nextIdx = Math.min(activeSectionIdx + 1, visibleSections.length - 1);
    const nextSec = visibleSections[nextIdx];
    if (nextSec) {
      scrollToSection(nextSec.id, nextIdx);
    }
  };

  const scrollToPrevSection = () => {
    const prevIdx = Math.max(activeSectionIdx - 1, 0);
    const prevSec = visibleSections[prevIdx];
    if (prevSec) {
      scrollToSection(prevSec.id, prevIdx);
    }
  };

  keydownHandlerRef.current = (e: KeyboardEvent) => {
    if (!isOpen) return;
    if (e.key === 'Escape') onClose();
    if (e.key === 'ArrowDown' && e.altKey) {
      e.preventDefault();
      scrollToNextSection();
    }
    if (e.key === 'ArrowUp' && e.altKey) {
      e.preventDefault();
      scrollToPrevSection();
    }
  };

  // Keyboard shortcut support in both the Admin shell and its same-origin saved-route iframe.
  useEffect(() => {
    const listener = sharedKeydownListenerRef.current;
    window.addEventListener('keydown', listener);
    return () => window.removeEventListener('keydown', listener);
  }, []);

  useEffect(() => () => {
    detachIframeInteractionsRef.current?.();
    detachIframeInteractionsRef.current = null;
  }, []);

  useEffect(() => {
    if (isOpen && page) return;
    detachIframeInteractionsRef.current?.();
    detachIframeInteractionsRef.current = null;
  }, [isOpen, page]);

  if (!isOpen || !page) return null;

  const getViewportWidth = () => {
    switch (viewport) {
      case 'mobile': return 'max-w-[390px]';
      case 'tablet': return 'max-w-[768px]';
      case 'desktop':
      default: return 'max-w-full';
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex flex-col bg-neutral-950/95 backdrop-blur-md animate-in fade-in select-none">
      {/* Top Reading Progress Bar */}
      <div className="h-1 w-full bg-neutral-900 absolute top-0 left-0 z-50">
        <div
          className="h-full bg-gradient-to-r from-indigo-500 via-purple-500 to-emerald-400 transition-all duration-150"
          style={{ width: `${scrollProgress}%` }}
        />
      </div>

      {/* Top Floating Control Bar */}
      <div className="h-16 px-4 sm:px-6 border-b border-neutral-800 bg-neutral-900/95 flex items-center justify-between flex-shrink-0 z-40">
        <div className="flex items-center gap-3 min-w-0">
          <div className="flex items-center gap-2 flex-shrink-0">
            <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping" />
            <span className="text-xs font-bold uppercase tracking-wider text-emerald-400 hidden sm:inline">Live Customer View</span>
          </div>
          <span className="text-neutral-700 hidden sm:inline">|</span>
          <h2 className="text-sm font-bold text-white truncate max-w-[200px] sm:max-w-[280px]">
            {page.title}
          </h2>
          {page.isHomepage ? (
            <span className="text-[11px] font-mono font-bold px-2 py-0.5 rounded bg-indigo-950 text-indigo-300 border border-indigo-700/80 flex items-center gap-1 shadow-xs">
              <Home className="w-3 h-3 text-indigo-400" />
              / (index.php)
            </span>
          ) : (
            <span className="text-[11px] font-mono px-2 py-0.5 rounded bg-neutral-800 text-neutral-400 hidden md:inline">
              /pages/{page.slug}
            </span>
          )}
          <span className="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-indigo-950/80 text-indigo-300 border border-indigo-800/60 hidden lg:inline">
            {visibleSections.length} Sections Loaded
          </span>
        </div>

        {/* Center: Quick Section Switcher & Viewport Selector */}
        <div className="flex items-center gap-2 sm:gap-3">
          {/* Toggle Section Outline Navigator */}
          <button
            onClick={() => setShowSectionNavigator(!showSectionNavigator)}
            className={`px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all ${
              showSectionNavigator
                ? 'bg-indigo-600/30 text-indigo-300 border border-indigo-500/40'
                : 'bg-neutral-800 text-neutral-300 hover:text-white border border-neutral-700'
            }`}
            title="Toggle Section Navigator Outline"
          >
            <ListOrdered className="w-3.5 h-3.5 text-indigo-400" />
            <span className="hidden md:inline">Outline ({visibleSections.length})</span>
          </button>

          {/* Viewport Switchers */}
          <div className="flex items-center gap-1 p-1 rounded-xl bg-neutral-950 border border-neutral-800">
            <button
              onClick={() => setViewport('desktop')}
              className={`p-1.5 sm:px-2.5 rounded-lg text-xs flex items-center gap-1.5 font-semibold transition-colors ${
                viewport === 'desktop' ? 'bg-indigo-600 text-white' : 'text-neutral-400 hover:text-white'
              }`}
              title="Desktop 100%"
            >
              <Monitor className="w-3.5 h-3.5" />
              <span className="hidden sm:inline">Desktop</span>
            </button>
            <button
              onClick={() => setViewport('tablet')}
              className={`p-1.5 sm:px-2.5 rounded-lg text-xs flex items-center gap-1.5 font-semibold transition-colors ${
                viewport === 'tablet' ? 'bg-indigo-600 text-white' : 'text-neutral-400 hover:text-white'
              }`}
              title="Tablet 768px"
            >
              <Tablet className="w-3.5 h-3.5" />
              <span className="hidden sm:inline">Tablet</span>
            </button>
            <button
              onClick={() => setViewport('mobile')}
              className={`p-1.5 sm:px-2.5 rounded-lg text-xs flex items-center gap-1.5 font-semibold transition-colors ${
                viewport === 'mobile' ? 'bg-indigo-600 text-white' : 'text-neutral-400 hover:text-white'
              }`}
              title="Mobile 390px"
            >
              <Smartphone className="w-3.5 h-3.5" />
              <span className="hidden sm:inline">Mobile</span>
            </button>
          </div>
        </div>

        {/* Right: Section Controls & Close */}
        <div className="flex items-center gap-1 sm:gap-2">
          {/* Homepage Status / Quick Action */}
          {page.isHomepage ? (
            <span className="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-xs">
              <Home className="w-3.5 h-3.5" />
              <span>Active Home (index.php)</span>
            </span>
          ) : (
            <button
              onClick={() => {
                onSetHomepage?.(page.id);
              }}
              className="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-neutral-300 hover:text-white bg-neutral-800 hover:bg-neutral-700 border border-neutral-700 transition-colors cursor-pointer"
              title="Set this page as the Storefront Home Page (index.php)"
            >
              <Home className="w-3.5 h-3.5 text-neutral-400" />
              <span>Set as Home</span>
            </button>
          )}
          {/* Section Step Prev / Next Buttons */}
          <div className="hidden sm:flex items-center gap-1 bg-neutral-950 border border-neutral-800 rounded-xl p-0.5">
            <button
              onClick={scrollToPrevSection}
              disabled={activeSectionIdx === 0}
              className="p-1.5 text-neutral-400 hover:text-white disabled:opacity-30 rounded-lg hover:bg-neutral-800"
              title="Previous Section (Alt+Up)"
            >
              <ChevronUp className="w-4 h-4" />
            </button>
            <span className="text-[11px] font-mono px-1.5 text-neutral-400">
              {activeSectionIdx + 1}/{visibleSections.length}
            </span>
            <button
              onClick={scrollToNextSection}
              disabled={activeSectionIdx === visibleSections.length - 1}
              className="p-1.5 text-neutral-400 hover:text-white disabled:opacity-30 rounded-lg hover:bg-neutral-800"
              title="Next Section (Alt+Down)"
            >
              <ChevronDown className="w-4 h-4" />
            </button>
          </div>

          {/* Close Button */}
          <button
            onClick={onClose}
            className="p-2 rounded-xl text-neutral-400 hover:text-white hover:bg-neutral-800 transition-colors"
            title="Close Preview (Esc)"
          >
            <X className="w-5 h-5" />
          </button>
        </div>
      </div>

      {/* Workspace Area: Stage & Floating Section Quick Navigator */}
      <div className="relative flex-1 flex min-h-0 overflow-hidden">
        {/* Main Viewport Scroll Container */}
        <div
          ref={scrollContainerRef}
          onScroll={handleScroll}
          className="flex-1 overflow-y-auto p-3 sm:p-6 md:p-8 flex justify-center bg-neutral-950/80 scroll-smooth"
        >
          <div className={`w-full ${getViewportWidth()} bg-neutral-900 border border-neutral-800 shadow-2xl rounded-3xl overflow-hidden flex flex-col transition-all duration-300 self-start select-text`}>
            <iframe
              ref={iframeRef}
              src={`/admin/pages/${encodeURIComponent(page.id)}/preview`}
              title={`Saved preview of ${page.title}`}
              onLoad={handleIframeLoad}
              className="w-full h-[calc(100vh-10rem)] min-h-[640px] border-0 bg-neutral-950"
            />
          </div>
        </div>

        {/* Floating Interactive Section Outline Menu (Right Dock) */}
        {showSectionNavigator && visibleSections.length > 0 && (
          <aside className="absolute bottom-6 right-6 z-40 max-w-xs bg-neutral-900/95 backdrop-blur-md border border-neutral-800 shadow-2xl rounded-2xl p-3 flex flex-col gap-1.5 transition-all">
            <div className="flex items-center justify-between px-2 pb-1.5 border-b border-neutral-800">
              <div className="flex items-center gap-1.5">
                <Layers className="w-3.5 h-3.5 text-indigo-400" />
                <span className="text-[11px] font-bold uppercase tracking-wider text-neutral-300">
                  Page Outline ({visibleSections.length})
                </span>
              </div>
              <button
                onClick={() => setShowSectionNavigator(false)}
                className="text-neutral-500 hover:text-white text-xs p-1"
                title="Hide Outline"
              >
                <X className="w-3 h-3" />
              </button>
            </div>

            <div className="max-h-60 overflow-y-auto space-y-1 pr-1">
              {visibleSections.map((sec, idx) => {
                const isActive = activeSectionIdx === idx;
                return (
                  <button
                    key={sec.id}
                    onClick={() => scrollToSection(sec.id, idx)}
                    className={`w-full text-left px-2.5 py-1.5 rounded-xl text-xs flex items-center justify-between gap-2 transition-all ${
                      isActive
                        ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-600/30'
                        : 'text-neutral-400 hover:text-white hover:bg-neutral-800'
                    }`}
                  >
                    <div className="truncate">
                      <span className="text-[10px] opacity-75 mr-1.5">{idx + 1}.</span>
                      <span className="truncate">{sec.title || sec.type.replace('_', ' ')}</span>
                    </div>
                    {isActive && <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse flex-shrink-0" />}
                  </button>
                );
              })}
            </div>

            {/* Quick Explore Prompt & Scroll Down Trigger */}
            <div className="pt-2 border-t border-neutral-800 flex items-center justify-between text-[11px] text-neutral-400 px-1">
              <span>Scroll or Click to Jump</span>
              <button
                onClick={scrollToNextSection}
                className="text-indigo-400 hover:text-indigo-300 font-semibold flex items-center gap-0.5"
              >
                <span>Next</span>
                <ChevronDown className="w-3.5 h-3.5" />
              </button>
            </div>
          </aside>
        )}
      </div>
    </div>
  );
};

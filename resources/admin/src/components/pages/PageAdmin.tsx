import React, { useEffect, useMemo, useRef, useState } from 'react';
import { CmsPage, PageDocument, PageDraftPayload, PageSection } from '../../types';
import { useAdmin } from '../../context/AdminContext';
import { readAdminBootstrap } from '../../integration/adminBootstrap';
import {
  archivePage,
  fetchPageDraft,
  publishPage,
  savePageDraft,
  unpublishPage,
} from '../../integration/pageData';
import { PageDraftAutosave, PageDraftAutosaveState } from '../../cms/pageDraftAutosave';
import { hasOwnContractField } from '../../cms/pageContract';
import { parseDraftImport } from '../../cms/pageImport';
import { repairLegacyCollectionTabs } from '../../cms/collectionTabMappings';
import { createAdminPageRuntimePayload, PageRuntimeProvider } from '../../cms/PageRuntimeContext';
import { SectionRenderer } from './SectionRenderer';
import { SectionInspector } from './SectionInspector';
import { AddSectionModal } from './AddSectionModal';
import { PagePreviewModal } from './PagePreviewModal';
import { DeleteConfirmationModal } from '../common/DeleteConfirmationModal';
import {
  LayoutTemplate,
  Plus,
  Search,
  Eye,
  Edit3,
  Copy,
  Trash2,
  ExternalLink,
  ChevronLeft,
  Monitor,
  Tablet,
  Smartphone,
  ArrowUp,
  ArrowDown,
  Layers,
  Save,
  CheckCircle2,
  FileCode2,
  Sliders,
  Sparkles,
  Zap,
  Globe,
  MoreVertical,
  SlidersHorizontal,
  FolderTree,
  AlertCircle,
  PanelLeftClose,
  PanelLeftOpen,
  PanelRightClose,
  PanelRightOpen,
  Maximize2,
  Minimize2
} from 'lucide-react';

type PageAutosaveTakeover = {
  controller: PageDraftAutosave<PageDocument> | null;
  pendingDocument: PageDocument | null;
  navigationIntent: number;
  openPreview: boolean;
};

export const PageAdmin: React.FC = () => {
  const {
    pages,
    savePage,
    updatePageSection,
    updatePageLocal,
    replacePageLocal,
    deletePage,
    duplicatePage,
    products,
    categories,
    collections,
    shopSettings,
    selectedPageId,
    setSelectedPageId,
    addToast
  } = useAdmin();

  // Mode state: null = Page Directory, non-null = Editing that Page ID
  const [editingPageId, setEditingPageId] = useState<string | null>(selectedPageId || null);
  const [activeSectionId, setActiveSectionId] = useState<string | null>(null);
  const [isAddSectionOpen, setIsAddSectionOpen] = useState(false);
  const [isPreviewModalOpen, setIsPreviewModalOpen] = useState(false);
  const [isOutlineCollapsed, setIsOutlineCollapsed] = useState(false);
  const [isInspectorCollapsed, setIsInspectorCollapsed] = useState(false);
  const [viewport, setViewport] = useState<'desktop' | 'tablet' | 'mobile'>('desktop');
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<'all' | 'published' | 'draft' | 'archived'>('all');
  const [isJsonModalOpen, setIsJsonModalOpen] = useState(false);
  const [jsonText, setJsonText] = useState('');
  const [pageToDelete, setPageToDelete] = useState<CmsPage | null>(null);
  const [sectionToDelete, setSectionToDelete] = useState<{ id: string; title: string; type: string } | null>(null);
  const autosaveRef = useRef<PageDraftAutosave<PageDocument> | null>(null);
  const autosavePageIdRef = useRef<string | null>(null);
  const autosaveUnsubscribeRef = useRef<(() => void) | null>(null);
  const detachedAutosavesRef = useRef(new Map<string, PageDraftAutosave<PageDocument>>());
  const pageTakeoversRef = useRef(new Map<string, PageAutosaveTakeover>());
  const navigationIntentRef = useRef(0);
  const [autosaveState, setAutosaveState] = useState<Readonly<PageDraftAutosaveState>>({
    dirty: false,
    saving: false,
    acknowledgedVersion: 0,
    conflict: null,
  });
  const laravelBootstrap = readAdminBootstrap();

  // Currently active page being edited
  const currentPage = pages.find(p => p.id === editingPageId);
  const activeSection = currentPage?.sections.find(s => s.id === activeSectionId) || null;
  const adminRuntimePayload = useMemo(() => currentPage
    ? createAdminPageRuntimePayload(currentPage, { products, categories, collections }, shopSettings.currency)
    : null,
  [currentPage, products, categories, collections, shopSettings.currency]);

  const toPageDocument = (page: CmsPage): PageDocument => ({
    title: page.title,
    slug: page.slug,
    template: page.template,
    sections: page.sections,
    ...(hasOwnContractField(page, 'description') ? { description: page.description } : {}),
    ...(hasOwnContractField(page, 'heroImage') ? { heroImage: page.heroImage } : {}),
    ...(hasOwnContractField(page, 'seoTitle') ? { seoTitle: page.seoTitle } : {}),
    ...(hasOwnContractField(page, 'seoDescription') ? { seoDescription: page.seoDescription } : {}),
  });

  const pageWithDocument = (
    page: CmsPage,
    document: PageDocument,
    metadata: Partial<CmsPage> = {},
  ): CmsPage => {
    const {
      title: _title,
      slug: _slug,
      description: _description,
      template: _template,
      heroImage: _heroImage,
      seoTitle: _seoTitle,
      seoDescription: _seoDescription,
      sections: _sections,
      ...identityAndLifecycle
    } = page;

    return {
      ...identityAndLifecycle,
      ...document,
      ...metadata,
    };
  };

  const pageFromDraft = (summary: CmsPage, draft: PageDraftPayload): CmsPage => pageWithDocument(
    summary,
    draft.document,
    {
      id: draft.pageId,
      status: draft.status,
      lockVersion: draft.lockVersion,
      schemaVersion: draft.schemaVersion,
      contentHash: draft.contentHash,
      hasPublishedSnapshot: draft.hasPublishedSnapshot,
      hasUnpublishedChanges: draft.hasUnpublishedChanges,
      updatedAt: draft.updatedAt.slice(0, 10),
    },
  );

  const mergeDraftMetadata = (pageId: string, draft: PageDraftPayload): void => {
    updatePageLocal(pageId, latest => ({
      ...latest,
      status: draft.status,
      lockVersion: draft.lockVersion,
      schemaVersion: draft.schemaVersion,
      contentHash: draft.contentHash,
      hasPublishedSnapshot: draft.hasPublishedSnapshot,
      hasUnpublishedChanges: draft.hasUnpublishedChanges,
      updatedAt: draft.updatedAt.slice(0, 10),
    }));
  };

  const reportSaveFailure = (error: unknown): void => {
    addToast({
      type: 'error',
      title: 'Page Save Failed',
      message: error instanceof Error ? error.message : 'Unable to save this page.',
    });
  };

  const makeAutosave = (pageId: string, lockVersion: number): PageDraftAutosave<PageDocument> => (
    new PageDraftAutosave<PageDocument>({
      delayMs: 600,
      lockVersion,
      save: async (document, acknowledgedVersion) => {
        try {
          const saved = await savePageDraft(
            laravelBootstrap,
            pageId,
            document,
            acknowledgedVersion,
          );
          mergeDraftMetadata(pageId, saved);
          return { lockVersion: saved.lockVersion };
        } catch (error) {
          reportSaveFailure(error);
          throw error;
        }
      },
    })
  );

  const disposeCurrentAutosave = (): void => {
    autosaveUnsubscribeRef.current?.();
    autosaveUnsubscribeRef.current = null;
    autosaveRef.current?.dispose();
    autosaveRef.current = null;
    autosavePageIdRef.current = null;
    setAutosaveState({
      dirty: false,
      saving: false,
      acknowledgedVersion: 0,
      conflict: null,
    });
  };

  const adoptAutosave = (
    pageId: string,
    controller: PageDraftAutosave<PageDocument>,
  ): PageDraftAutosave<PageDocument> => {
    disposeCurrentAutosave();
    autosaveRef.current = controller;
    autosavePageIdRef.current = pageId;
    autosaveUnsubscribeRef.current = controller.subscribe(setAutosaveState);
    return controller;
  };

  const ownAutosave = (pageId: string, lockVersion: number): PageDraftAutosave<PageDocument> => (
    adoptAutosave(pageId, makeAutosave(pageId, lockVersion))
  );

  const scheduleDetachedPage = (page: CmsPage): void => {
    let controller = detachedAutosavesRef.current.get(page.id);
    if (!controller) {
      controller = makeAutosave(page.id, page.lockVersion ?? 1);
      detachedAutosavesRef.current.set(page.id, controller);
    }
    controller.schedule(toPageDocument(page));
    void controller.flush()
      .then(() => {
        if (controller?.getState().dirty) return;
        controller?.dispose();
        if (detachedAutosavesRef.current.get(page.id) === controller) {
          detachedAutosavesRef.current.delete(page.id);
        }
      })
      .catch(() => undefined);
  };

  const reconcileDetachedAutosave = async (pageId: string): Promise<boolean> => {
    const controller = detachedAutosavesRef.current.get(pageId);
    if (!controller) return true;

    try {
      await controller.flush();
    } catch {
      return false;
    }

    if (controller.getState().dirty) return false;
    controller.dispose();
    if (detachedAutosavesRef.current.get(pageId) === controller) {
      detachedAutosavesRef.current.delete(pageId);
    }
    return true;
  };

  const schedulePage = (page: CmsPage): void => {
    const takeover = pageTakeoversRef.current.get(page.id);
    if (takeover) {
      const document = toPageDocument(page);
      if (takeover.controller) takeover.controller.schedule(document);
      else takeover.pendingDocument = document;
      return;
    }

    if (autosavePageIdRef.current === page.id && autosaveRef.current) {
      autosaveRef.current.schedule(toPageDocument(page));
      return;
    }

    // A managed upload may complete after its Inspector has been closed or a
    // different page is open. Persist the captured target using its latest
    // functional page state without ever applying it to the current page.
    scheduleDetachedPage(page);
  };

  const flushCurrentPage = async (): Promise<boolean> => {
    const controller = autosaveRef.current;
    if (!controller) return true;

    try {
      await controller.flush();
      return true;
    } catch {
      return false;
    }
  };

  const hydrateEditor = async (page: CmsPage, openPreview = false): Promise<void> => {
    const navigationIntent = navigationIntentRef.current + 1;
    navigationIntentRef.current = navigationIntent;
    const existingTakeover = pageTakeoversRef.current.get(page.id);
    if (existingTakeover) {
      existingTakeover.navigationIntent = navigationIntent;
      existingTakeover.openPreview = openPreview;
      return;
    }
    const takeover: PageAutosaveTakeover = {
      controller: null,
      pendingDocument: null,
      navigationIntent,
      openPreview,
    };
    pageTakeoversRef.current.set(page.id, takeover);
    const isLatestNavigation = (): boolean => navigationIntentRef.current === takeover.navigationIntent;

    try {
      if (autosavePageIdRef.current) {
        const flushed = await flushCurrentPage();
        if (!flushed || !isLatestNavigation()) return;
      }
      const reconciled = await reconcileDetachedAutosave(page.id);
      if (!reconciled || !isLatestNavigation()) return;

      let draft = await fetchPageDraft(laravelBootstrap, page.id);
      if (!isLatestNavigation()) return;
      while (true) {
        if (!takeover.controller && takeover.pendingDocument) {
          takeover.controller = makeAutosave(page.id, draft.lockVersion);
          takeover.controller.schedule(takeover.pendingDocument);
          takeover.pendingDocument = null;
        }

        if (takeover.controller?.getState().dirty) {
          try {
            await takeover.controller.flush();
          } catch {
            return;
          }
          if (!isLatestNavigation()) return;
          draft = await fetchPageDraft(laravelBootstrap, page.id);
          if (!isLatestNavigation()) return;
          continue;
        }

        if (takeover.controller
          && takeover.controller.getState().acknowledgedVersion !== draft.lockVersion) {
          takeover.controller.dispose();
          takeover.controller = null;
        }

        const controller = takeover.controller ?? makeAutosave(page.id, draft.lockVersion);
        takeover.controller = null;
        const hydrated = pageFromDraft(page, draft);
        replacePageLocal(hydrated);
        adoptAutosave(page.id, controller);
        setEditingPageId(page.id);
        setActiveSectionId(hydrated.sections[0]?.id ?? null);
        if (takeover.openPreview) setIsPreviewModalOpen(true);
        return;
      }
    } catch (error) {
      if (isLatestNavigation()) reportSaveFailure(error);
    } finally {
      if (pageTakeoversRef.current.get(page.id) === takeover) {
        pageTakeoversRef.current.delete(page.id);
      }

      if (takeover.controller) {
        const state = takeover.controller.getState();
        if (state.dirty || state.saving || state.conflict) {
          detachedAutosavesRef.current.set(page.id, takeover.controller);
        } else {
          takeover.controller.dispose();
        }
      } else if (takeover.pendingDocument) {
        scheduleDetachedPage(pageWithDocument(page, takeover.pendingDocument));
      }
    }
  };

  useEffect(() => {
    if (!editingPageId || !currentPage || autosavePageIdRef.current === editingPageId) return;
    let active = true;
    void fetchPageDraft(laravelBootstrap, editingPageId)
      .then(draft => {
        if (!active) return;
        const hydrated = pageFromDraft(currentPage, draft);
        replacePageLocal(hydrated);
        ownAutosave(editingPageId, draft.lockVersion);
      })
      .catch(error => {
        if (active) reportSaveFailure(error);
      });

    return () => {
      active = false;
    };
  // Initialization only: saves are scheduled exclusively by user-event handlers.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editingPageId, currentPage?.id]);

  useEffect(() => {
    if (!autosaveState.dirty && !autosaveState.conflict) return;
    const protectUnsavedDraft = (event: BeforeUnloadEvent) => {
      event.preventDefault();
      event.returnValue = '';
    };
    window.addEventListener('beforeunload', protectUnsavedDraft);
    return () => window.removeEventListener('beforeunload', protectUnsavedDraft);
  }, [autosaveState.dirty, autosaveState.conflict]);

  useEffect(() => () => {
    autosaveUnsubscribeRef.current?.();
    autosaveRef.current?.dispose();
    detachedAutosavesRef.current.forEach(controller => controller.dispose());
    detachedAutosavesRef.current.clear();
    pageTakeoversRef.current.forEach(takeover => takeover.controller?.dispose());
    pageTakeoversRef.current.clear();
  }, []);

  // Handler for creating a brand new page
  const handleCreateNewPage = async () => {
    const newPage: CmsPage = {
      id: 'page-' + Date.now(),
      title: 'New Landing Page',
      slug: 'new-landing-page-' + Math.floor(Math.random() * 1000),
      description: 'Custom editorial landing page with media & structured sections.',
      status: 'draft',
      template: 'standard',
      createdAt: new Date().toISOString().split('T')[0],
      updatedAt: new Date().toISOString().split('T')[0],
      sections: [
        {
          id: 'sec-' + Date.now() + '-hero',
          type: 'hero_banner',
          title: 'Welcome to Our New Experience',
          subtitle: 'NEW RELEASE',
          content: 'Discover acoustic perfection with our new handcrafted audio system.',
          mediaUrl: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=1600&auto=format&fit=crop&q=80',
          mediaType: 'image',
          buttonText: 'Explore Now',
          buttonLink: '/collections/all',
          textAlignment: 'center',
          backgroundColor: 'bg-neutral-950',
          textColor: 'light',
          paddingY: 'spacious',
          overlayOpacity: 50,
          isVisible: true
        }
      ]
    };
    const saved = await savePage(newPage);
    if (saved) {
      await hydrateEditor(saved);
    }
  };

  // Section Manipulation in Active Page
  const handleAddSection = (newSection: PageSection) => {
    if (!currentPage) return;
    const updated = updatePageLocal(currentPage.id, latest => ({
      ...latest,
      sections: [...latest.sections, newSection],
      updatedAt: new Date().toISOString().split('T')[0],
    }));
    if (!updated) return;
    schedulePage(updated);
    setActiveSectionId(newSection.id);
  };

  const handleUpdateSection = (updatedSection: PageSection) => {
    if (!currentPage) return;
    void updatePageSection(currentPage.id, updatedSection.id, () => updatedSection)
      .then(updated => {
        if (updated) schedulePage(updated);
      });
  };

  const handleDeleteSection = (sectionId: string) => {
    if (!currentPage) return;
    const updatedPage = updatePageLocal(currentPage.id, latest => ({
      ...latest,
      sections: latest.sections.filter(section => section.id !== sectionId),
      updatedAt: new Date().toISOString().split('T')[0],
    }));
    if (!updatedPage) return;
    schedulePage(updatedPage);
    if (activeSectionId === sectionId) {
      setActiveSectionId(updatedPage.sections[0]?.id || null);
    }
  };

  const requestDeleteSection = (sectionId: string) => {
    const target = currentPage?.sections.find(section => section.id === sectionId);
    setSectionToDelete({
      id: sectionId,
      title: target?.title || target?.type.replaceAll('_', ' ').toUpperCase() || 'Selected Section',
      type: target?.type || 'Section',
    });
  };

  const handleMoveSection = (index: number, direction: 'up' | 'down') => {
    if (!currentPage) return;
    const updatedPage = updatePageLocal(currentPage.id, latest => {
      const sections = [...latest.sections];
      const targetIdx = direction === 'up' ? index - 1 : index + 1;
      if (targetIdx < 0 || targetIdx >= sections.length) return latest;
      [sections[index], sections[targetIdx]] = [sections[targetIdx], sections[index]];
      return {
        ...latest,
        sections,
        updatedAt: new Date().toISOString().split('T')[0],
      };
    });
    if (updatedPage) schedulePage(updatedPage);
  };

  const handleDuplicateSection = (sectionId: string) => {
    if (!currentPage) return;
    const targetIdx = currentPage.sections.findIndex(s => s.id === sectionId);
    if (targetIdx === -1) return;
    const target = currentPage.sections[targetIdx];
    const duplicated: PageSection = {
      ...target,
      id: 'sec-' + Date.now() + '-' + Math.random().toString(36).substring(2, 6),
      title: `${target.title} (Copy)`,
      items: target.items?.map(it => ({
        ...it,
        id: 'item-' + Math.random().toString(36).substring(2, 6)
      }))
    };
    const updatedPage = updatePageLocal(currentPage.id, latest => {
      const latestTargetIndex = latest.sections.findIndex(section => section.id === sectionId);
      if (latestTargetIndex === -1) return latest;
      const sections = [...latest.sections];
      sections.splice(latestTargetIndex + 1, 0, duplicated);
      return {
        ...latest,
        sections,
        updatedAt: new Date().toISOString().split('T')[0],
      };
    });
    if (!updatedPage) return;
    schedulePage(updatedPage);
    setActiveSectionId(duplicated.id);
  };

  // Open JSON Exporter
  const handleOpenJson = () => {
    if (!currentPage) return;
    setJsonText(JSON.stringify(toPageDocument(currentPage), null, 2));
    setIsJsonModalOpen(true);
  };

  const handleImportJson = async () => {
    if (!currentPage) return;

    try {
      const parsed = parseDraftImport(jsonText, currentPage.id);
      if (!await flushCurrentPage()) return;
      const updated = updatePageLocal(currentPage.id, latest => pageWithDocument(latest, parsed, {
        updatedAt: new Date().toISOString().split('T')[0],
      }));
      if (!updated) return;
      schedulePage(updated);
      if (!await flushCurrentPage()) return;
      setIsJsonModalOpen(false);
      addToast({ type: 'success', title: 'Layout Imported', message: 'Page template loaded successfully.' });
    } catch (e: any) {
      addToast({ type: 'error', title: 'Import Failed', message: e.message || 'Malformed JSON format.' });
    }
  };

  const handleBackToDirectory = async (): Promise<void> => {
    if (!await flushCurrentPage()) return;
    disposeCurrentAutosave();
    setSelectedPageId(null);
    setEditingPageId(null);
    setActiveSectionId(null);
  };

  const handlePreview = async (): Promise<void> => {
    if (!await flushCurrentPage()) return;
    setIsPreviewModalOpen(true);
  };

  const handleTitleChange = (title: string): void => {
    if (!currentPage) return;
    const updated = updatePageLocal(currentPage.id, latest => ({
      ...latest,
      title,
      updatedAt: new Date().toISOString().split('T')[0],
    }));
    if (updated) schedulePage(updated);
  };

  const repairLegacyCollectionTabsBeforePublish = (): void => {
    if (!currentPage) return;

    let repaired = false;
    const updated = updatePageLocal(currentPage.id, latest => {
      const sections = latest.sections.map(section => {
        const resolved = repairLegacyCollectionTabs(section, categories, collections);
        repaired ||= resolved !== section;
        return resolved;
      });

      return repaired ? {
        ...latest,
        sections,
        updatedAt: new Date().toISOString().split('T')[0],
      } : latest;
    });

    if (repaired && updated) schedulePage(updated);
  };

  const publishCurrentPage = async (): Promise<boolean> => {
    if (!currentPage) return false;
    repairLegacyCollectionTabsBeforePublish();
    if (!await flushCurrentPage()) return false;
    const controller = autosaveRef.current;
    if (!controller) return false;

    try {
      const saved = await publishPage(
        laravelBootstrap,
        currentPage.id,
        controller.getState().acknowledgedVersion,
      );
      replacePageLocal(saved);
      addToast({
        type: 'success',
        title: 'Page Published',
        message: `Published "${saved.title}" successfully.`,
      });
      return true;
    } catch (error) {
      addToast({
        type: 'error',
        title: 'Publish Failed',
        message: error instanceof Error ? error.message : 'Unable to publish this page.',
      });
      return false;
    }
  };

  const handleStatusChange = async (status: CmsPage['status']): Promise<void> => {
    if (!currentPage || status === currentPage.status || !await flushCurrentPage()) return;

    try {
      if (status === 'published') {
        await publishCurrentPage();
        return;
      }

      const saved = status === 'draft'
        ? await unpublishPage(laravelBootstrap, currentPage.id)
        : await archivePage(laravelBootstrap, currentPage.id);
      replacePageLocal(saved);
    } catch (error) {
      addToast({
        type: 'error',
        title: 'Page Status Failed',
        message: error instanceof Error ? error.message : 'Unable to change this page status.',
      });
    }
  };

  // Filtered pages for directory view
  const filteredPages = pages.filter(p => {
    const matchSearch = p.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
      p.slug.toLowerCase().includes(searchQuery.toLowerCase());
    const matchStatus = statusFilter === 'all' || p.status === statusFilter;
    return matchSearch && matchStatus;
  });

  // -------------------------------------------------------------
  // VIEW 1: PAGE DIRECTORY (List of all CMS Pages)
  // -------------------------------------------------------------
  if (!editingPageId || !currentPage) {
    return (
      <div id="page-admin-directory" className="space-y-6 animate-in fade-in">
        {/* Header Title & Actions */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2">
              <span className="p-2 rounded-xl bg-indigo-600 text-white shadow-md">
                <LayoutTemplate className="w-5 h-5" />
              </span>
              <div>
                <h1 className="text-xl sm:text-2xl font-bold text-neutral-900 dark:text-white tracking-tight">
                  Design Layout & CMS Pages
                </h1>
                <p className="text-xs sm:text-sm text-neutral-500 dark:text-neutral-400">
                  Build custom landing pages, story lookbooks, and brand editorial layouts with visual sections
                </p>
              </div>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <button
              onClick={handleCreateNewPage}
              className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs bg-indigo-600 text-white hover:bg-indigo-500 shadow-md transition-all duration-200 transform hover:-translate-y-0.5"
            >
              <Plus className="w-4 h-4" />
              <span>Create Layout Page</span>
            </button>
          </div>
        </div>

        {/* Metric Summary Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase">Total Pages</span>
              <span className="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400">
                <LayoutTemplate className="w-4 h-4" />
              </span>
            </div>
            <p className="text-2xl font-black text-neutral-900 dark:text-white mt-2">{pages.length}</p>
            <p className="text-[11px] text-neutral-400 mt-1">Custom store layouts</p>
          </div>

          <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase">Published Live</span>
              <span className="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400">
                <CheckCircle2 className="w-4 h-4" />
              </span>
            </div>
            <p className="text-2xl font-black text-neutral-900 dark:text-white mt-2">
              {pages.filter(p => p.status === 'published').length}
            </p>
            <p className="text-[11px] text-emerald-600 dark:text-emerald-400 mt-1">Live on storefront routes</p>
          </div>

          <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase">Draft Workspaces</span>
              <span className="p-2 rounded-xl bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400">
                <Sliders className="w-4 h-4" />
              </span>
            </div>
            <p className="text-2xl font-black text-neutral-900 dark:text-white mt-2">
              {pages.filter(p => p.status === 'draft').length}
            </p>
            <p className="text-[11px] text-neutral-400 mt-1">In progress & staged</p>
          </div>

          <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase">Active Sections</span>
              <span className="p-2 rounded-xl bg-cyan-50 dark:bg-cyan-950 text-cyan-600 dark:text-cyan-400">
                <Layers className="w-4 h-4" />
              </span>
            </div>
            <p className="text-2xl font-black text-neutral-900 dark:text-white mt-2">
              {pages.reduce((acc, p) => acc + p.sections.length, 0)}
            </p>
            <p className="text-[11px] text-neutral-400 mt-1">Total modular blocks</p>
          </div>
        </div>

        {/* Filter Bar */}
        <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 flex flex-col sm:flex-row items-center justify-between gap-3">
          <div className="relative w-full sm:w-80">
            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
            <input
              type="text"
              value={searchQuery}
              onChange={e => setSearchQuery(e.target.value)}
              placeholder="Search by page title or slug..."
              className="w-full pl-9 pr-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-xs text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <div className="flex items-center gap-1.5 w-full sm:w-auto overflow-x-auto">
            {(['all', 'published', 'draft', 'archived'] as const).map(st => (
              <button
                key={st}
                onClick={() => setStatusFilter(st)}
                className={`px-3 py-1.5 rounded-xl text-xs font-semibold uppercase tracking-wider transition-all whitespace-nowrap ${
                  statusFilter === st
                    ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 shadow-xs'
                    : 'text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                }`}
              >
                {st}
              </button>
            ))}
          </div>
        </div>

        {/* Pages Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {filteredPages.map(page => (
            <div
              key={page.id}
              className="rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs hover:shadow-xl hover:border-indigo-500/40 transition-all duration-300 flex flex-col overflow-hidden group"
            >
              {/* Card Header / Banner Preview */}
              <div className="relative h-40 bg-neutral-950 overflow-hidden">
                {page.heroImage || page.sections.find(s => s.mediaUrl)?.mediaUrl ? (
                  <img
                    src={page.heroImage || page.sections.find(s => s.mediaUrl)?.mediaUrl}
                    alt={page.title}
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 opacity-80"
                    referrerPolicy="no-referrer"
                  />
                ) : (
                  <div className="w-full h-full bg-gradient-to-br from-indigo-950 via-neutral-900 to-black flex items-center justify-center">
                    <LayoutTemplate className="w-10 h-10 text-neutral-700" />
                  </div>
                )}

                <div className="absolute inset-0 bg-gradient-to-t from-neutral-950 via-neutral-950/40 to-transparent" />

                {/* Status Badge */}
                <div className="absolute top-3 left-3 flex items-center gap-2">
                  <span className={`text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full backdrop-blur-md shadow-md ${
                    page.status === 'published'
                      ? 'bg-emerald-500/90 text-white'
                      : page.status === 'draft'
                      ? 'bg-amber-500/90 text-white'
                      : 'bg-neutral-800/90 text-neutral-300'
                  }`}>
                    {page.status}
                  </span>
                  <span className="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-black/60 text-neutral-300 backdrop-blur-md border border-white/10">
                    {page.sections.length} sections
                  </span>
                </div>
              </div>

              {/* Card Body */}
              <div className="p-6 flex-1 flex flex-col justify-between">
                <div>
                  <div className="flex items-baseline justify-between gap-2 mb-1">
                    <h3 className="text-base font-bold text-neutral-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors truncate">
                      {page.title}
                    </h3>
                  </div>

                  <p className="text-xs font-mono text-neutral-400 dark:text-neutral-500 mb-2">
                    /pages/{page.slug}
                  </p>

                  <p className="text-xs text-neutral-500 dark:text-neutral-400 line-clamp-2 leading-relaxed mb-4">
                    {page.description || 'No description provided.'}
                  </p>

                  {/* Section Badges */}
                  <div className="flex flex-wrap gap-1.5 mb-4">
                    {page.sections.slice(0, 4).map((sec, idx) => (
                      <span
                        key={sec.id || idx}
                        className="text-[10px] px-2 py-0.5 rounded-md bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 font-medium"
                      >
                        {sec.type.replace('_', ' ')}
                      </span>
                    ))}
                    {page.sections.length > 4 && (
                      <span className="text-[10px] px-1.5 py-0.5 rounded-md bg-neutral-100 dark:bg-neutral-800 text-neutral-400">
                        +{page.sections.length - 4} more
                      </span>
                    )}
                  </div>
                </div>

                {/* Card Actions Footer */}
                <div className="pt-4 border-t border-neutral-100 dark:border-neutral-800/80 flex items-center justify-between">
                  <span className="text-[10px] text-neutral-400 font-medium">
                    Updated {page.updatedAt}
                  </span>

                  <div className="flex items-center gap-1.5">
                    <button
                      onClick={() => { void hydrateEditor(page, true); }}
                      className="p-2 rounded-xl text-neutral-500 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
                      title="Preview Live Page"
                    >
                      <Eye className="w-4 h-4" />
                    </button>
                    <button
                      onClick={() => duplicatePage(page.id)}
                      className="p-2 rounded-xl text-neutral-500 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
                      title="Clone Page Layout"
                    >
                      <Copy className="w-4 h-4" />
                    </button>
                    <button
                      onClick={() => setPageToDelete(page)}
                      className="p-2 rounded-xl text-neutral-500 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-colors"
                      title="Delete Page"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                    <button
                      onClick={() => { void hydrateEditor(page); }}
                      className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl font-bold text-xs bg-indigo-600 text-white hover:bg-indigo-500 transition-colors shadow-xs"
                    >
                      <Edit3 className="w-3.5 h-3.5" />
                      <span>Edit Layout</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  // -------------------------------------------------------------
  // VIEW 2: VISUAL LAYOUT EDITOR & SECTION BUILDER
  // -------------------------------------------------------------
  const getCanvasWidthClass = () => {
    switch (viewport) {
      case 'mobile': return 'max-w-[390px]';
      case 'tablet': return 'max-w-[768px]';
      case 'desktop':
      default: return 'max-w-full';
    }
  };

  return (
    <div id="page-visual-builder" className="h-[calc(100vh-6.5rem)] flex flex-col -mx-4 sm:-mx-6 lg:-mx-8 -mb-4 sm:-mb-6 lg:-mb-8 mt-3 sm:mt-4 pt-0.5 bg-neutral-100 dark:bg-neutral-950 overflow-hidden animate-in fade-in">
      {/* 1. Top Builder Toolbar */}
      <div className="h-16 px-4 sm:px-6 border-y border-neutral-200 dark:border-neutral-800 bg-white/95 dark:bg-neutral-900/95 backdrop-blur-md flex items-center justify-between z-20 shrink-0 shadow-2xs">
        {/* Left: Back & Title Slug */}
        <div className="flex items-center gap-3 min-w-0">
          <button
            onClick={() => { void handleBackToDirectory(); }}
            className="flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-semibold text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
          >
            <ChevronLeft className="w-4 h-4" />
            <span className="hidden sm:inline">Pages</span>
          </button>

          <div className="h-6 w-px bg-neutral-200 dark:border-neutral-800" />

          <div className="flex items-center gap-2 min-w-0">
            <input
              type="text"
              value={currentPage.title}
              onChange={e => handleTitleChange(e.target.value)}
              className="font-bold text-sm sm:text-base text-neutral-900 dark:text-white bg-transparent border-b border-transparent hover:border-neutral-300 dark:hover:border-neutral-700 focus:border-indigo-500 focus:outline-hidden px-1 py-0.5 truncate"
            />
            <span className="hidden md:inline-block text-[11px] font-mono text-neutral-400 bg-neutral-100 dark:bg-neutral-800 px-2 py-0.5 rounded-md">
              /pages/{currentPage.slug}
            </span>
          </div>
        </div>

        {/* Center: Viewport Switchers & Zen Canvas Switch */}
        <div className="hidden md:flex items-center gap-1.5 p-1 rounded-xl bg-neutral-100 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700">
          <button
            onClick={() => setViewport('desktop')}
            className={`p-1.5 px-2.5 rounded-lg text-xs flex items-center gap-1 font-semibold transition-all ${
              viewport === 'desktop'
                ? 'bg-white dark:bg-neutral-900 text-indigo-600 dark:text-indigo-400 shadow-xs'
                : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
            }`}
            title="Desktop Full Screen"
          >
            <Monitor className="w-3.5 h-3.5" />
            <span>Desktop</span>
          </button>
          <button
            onClick={() => setViewport('tablet')}
            className={`p-1.5 px-2.5 rounded-lg text-xs flex items-center gap-1 font-semibold transition-all ${
              viewport === 'tablet'
                ? 'bg-white dark:bg-neutral-900 text-indigo-600 dark:text-indigo-400 shadow-xs'
                : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
            }`}
            title="Tablet 768px"
          >
            <Tablet className="w-3.5 h-3.5" />
            <span>Tablet</span>
          </button>
          <button
            onClick={() => setViewport('mobile')}
            className={`p-1.5 px-2.5 rounded-lg text-xs flex items-center gap-1 font-semibold transition-all ${
              viewport === 'mobile'
                ? 'bg-white dark:bg-neutral-900 text-indigo-600 dark:text-indigo-400 shadow-xs'
                : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
            }`}
            title="Mobile 390px"
          >
            <Smartphone className="w-3.5 h-3.5" />
            <span>Mobile</span>
          </button>

          <div className="h-4 w-px bg-neutral-200 dark:bg-neutral-700 mx-0.5" />

          {/* Quick Zen / Full Desktop Canvas Mode Button */}
          <button
            onClick={() => {
              const shouldCollapse = !(isOutlineCollapsed && isInspectorCollapsed);
              setIsOutlineCollapsed(shouldCollapse);
              setIsInspectorCollapsed(shouldCollapse);
            }}
            className={`p-1.5 px-2.5 rounded-lg text-xs flex items-center gap-1.5 font-semibold transition-all ${
              isOutlineCollapsed && isInspectorCollapsed
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'text-neutral-600 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-200/60 dark:hover:bg-neutral-700/60'
            }`}
            title={
              isOutlineCollapsed && isInspectorCollapsed
                ? 'Restore Sidebar Panels'
                : 'Full Canvas View (Collapse Both Outline and Inspector)'
            }
          >
            {isOutlineCollapsed && isInspectorCollapsed ? (
              <>
                <Minimize2 className="w-3.5 h-3.5" />
                <span className="hidden xl:inline">Restore</span>
              </>
            ) : (
              <>
                <Maximize2 className="w-3.5 h-3.5" />
                <span className="hidden xl:inline">Full Canvas</span>
              </>
            )}
          </button>
        </div>

        {/* Right: Actions */}
        <div className="flex items-center gap-2">
          {/* Status selector */}
          <select
            value={currentPage.status}
            onChange={e => { void handleStatusChange(e.target.value as CmsPage['status']); }}
            className={`px-3 py-1.5 text-xs font-bold uppercase tracking-wider rounded-xl border ${
              currentPage.status === 'published'
                ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border-emerald-300 dark:border-emerald-800'
                : 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 border-amber-300 dark:border-amber-800'
            }`}
          >
            <option value="published">Published</option>
            <option value="draft">Draft</option>
            <option value="archived">Archived</option>
          </select>

          {currentPage.status === 'published'
            && (Boolean(currentPage.hasUnpublishedChanges) || autosaveState.dirty) && (
            <button
              onClick={() => { void publishCurrentPage(); }}
              className="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl font-bold text-xs bg-indigo-600 text-white hover:bg-indigo-500 shadow-md transition-colors"
            >
              <Save className="w-4 h-4" />
              <span>Publish Changes</span>
            </button>
          )}

          {/* JSON schema */}
          <button
            onClick={handleOpenJson}
            className="p-2 rounded-xl text-neutral-500 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
            title="Export / Import JSON Layout"
          >
            <FileCode2 className="w-4 h-4" />
          </button>

          {/* Live Preview Button */}
          <button
            onClick={() => { void handlePreview(); }}
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-200 hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors"
          >
            <Eye className="w-3.5 h-3.5" />
            <span className="hidden sm:inline">Preview</span>
          </button>

          {/* Add Section Trigger */}
          <button
            onClick={() => setIsAddSectionOpen(true)}
            className="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl font-bold text-xs bg-indigo-600 text-white hover:bg-indigo-500 shadow-md transition-colors"
          >
            <Plus className="w-4 h-4" />
            <span>Add Section</span>
          </button>
        </div>
      </div>

      {/* 2. Main 3-Column Studio Workspace */}
      <div className="flex-1 flex min-h-0 overflow-hidden">
        {/* Left Panel: Section Outline Tree (Collapsible) */}
        {!isOutlineCollapsed ? (
          <div className="w-72 flex-shrink-0 flex flex-col border-r border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 transition-all duration-300">
            <div className="p-3.5 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between bg-neutral-50/60 dark:bg-neutral-950/60">
              <div className="flex items-center gap-2">
                <Layers className="w-4 h-4 text-indigo-500" />
                <span className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
                  Section Outline ({currentPage.sections.length})
                </span>
              </div>
              <div className="flex items-center gap-1">
                <button
                  onClick={() => setIsAddSectionOpen(true)}
                  className="p-1.5 rounded-lg text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950 transition-colors"
                  title="Add Section"
                >
                  <Plus className="w-4 h-4" />
                </button>
                <button
                  onClick={() => setIsOutlineCollapsed(true)}
                  className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
                  title="Collapse Section Outline"
                >
                  <PanelLeftClose className="w-4 h-4" />
                </button>
              </div>
            </div>

            <div className="flex-1 overflow-y-auto p-3 space-y-2">
              {currentPage.sections.map((section, idx) => {
                const isActive = activeSectionId === section.id;
                return (
                  <div
                    key={section.id}
                    onClick={() => setActiveSectionId(section.id)}
                    className={`group p-3 rounded-2xl border transition-all duration-200 cursor-pointer ${
                      isActive
                        ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-500 ring-1 ring-indigo-500 shadow-xs'
                        : 'bg-white dark:bg-neutral-950 border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700'
                    }`}
                  >
                    <div className="flex items-center justify-between gap-2 mb-1.5">
                      <span className="text-[10px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                        {idx + 1}. {section.type.replace(/_/g, ' ')}
                      </span>

                      <div className="flex items-center gap-1 opacity-60 group-hover:opacity-100 transition-opacity">
                        <button
                          onClick={e => {
                            e.stopPropagation();
                            handleMoveSection(idx, 'up');
                          }}
                          disabled={idx === 0}
                          className="p-1 rounded hover:bg-neutral-200 dark:hover:bg-neutral-800 disabled:opacity-30"
                          title="Move Up"
                        >
                          <ArrowUp className="w-3 h-3" />
                        </button>
                        <button
                          onClick={e => {
                            e.stopPropagation();
                            handleMoveSection(idx, 'down');
                          }}
                          disabled={idx === currentPage.sections.length - 1}
                          className="p-1 rounded hover:bg-neutral-200 dark:hover:bg-neutral-800 disabled:opacity-30"
                          title="Move Down"
                        >
                          <ArrowDown className="w-3 h-3" />
                        </button>
                        <button
                          onClick={e => {
                            e.stopPropagation();
                            handleDuplicateSection(section.id);
                          }}
                          className="p-1 rounded hover:bg-neutral-200 dark:hover:bg-neutral-800 text-neutral-500"
                          title="Duplicate Section"
                        >
                          <Copy className="w-3 h-3" />
                        </button>
                        <button
                          onClick={e => {
                            e.stopPropagation();
                            requestDeleteSection(section.id);
                          }}
                          className="p-1 rounded hover:bg-rose-100 dark:hover:bg-rose-950/40 text-rose-500"
                          title="Delete Section"
                        >
                          <Trash2 className="w-3 h-3" />
                        </button>
                      </div>
                    </div>

                    <p className="text-xs font-semibold text-neutral-900 dark:text-white truncate">
                      {section.title || 'Untitled Section'}
                    </p>

                    <div className="flex items-center justify-between text-[11px] text-neutral-400 mt-2">
                      <span className="truncate max-w-[120px]">
                        {section.subtitle || 'No eyebrow'}
                      </span>
                      <span className={`w-2 h-2 rounded-full ${section.isVisible ? 'bg-emerald-500' : 'bg-neutral-400'}`} />
                    </div>
                  </div>
                );
              })}

              {currentPage.sections.length === 0 && (
                <div className="p-6 text-center text-neutral-400 border border-dashed border-neutral-300 dark:border-neutral-800 rounded-2xl">
                  <AlertCircle className="w-8 h-8 mx-auto mb-2 text-neutral-400" />
                  <p className="text-xs font-semibold">No Sections Yet</p>
                  <button
                    onClick={() => setIsAddSectionOpen(true)}
                    className="mt-3 px-3 py-1.5 text-xs font-bold rounded-xl bg-indigo-600 text-white"
                  >
                    + Add First Section
                  </button>
                </div>
              )}
            </div>
          </div>
        ) : (
          /* Collapsed Section Outline Rail */
          <div className="w-12 flex-shrink-0 flex flex-col items-center justify-between py-3 border-r border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 transition-all duration-300 z-10 select-none">
            <div className="flex flex-col items-center gap-3">
              <button
                onClick={() => setIsOutlineCollapsed(false)}
                className="p-2 rounded-xl text-neutral-500 dark:text-neutral-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/80 transition-all"
                title="Expand Section Outline"
              >
                <PanelLeftOpen className="w-4 h-4" />
              </button>
              <div
                onClick={() => setIsOutlineCollapsed(false)}
                className="[writing-mode:vertical-lr] rotate-180 py-4 flex items-center gap-2 text-[11px] font-bold tracking-wider text-neutral-400 hover:text-indigo-500 uppercase cursor-pointer transition-colors"
                title="Click to expand outline"
              >
                <Layers className="w-3.5 h-3.5 text-indigo-500" />
                <span>Outline ({currentPage.sections.length})</span>
              </div>
            </div>

            <button
              onClick={() => setIsAddSectionOpen(true)}
              className="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900 border border-indigo-200 dark:border-indigo-800 transition-all"
              title="Add Section"
            >
              <Plus className="w-4 h-4" />
            </button>
          </div>
        )}

        {/* Center Stage: Interactive Live Storefront Canvas */}
        <div className="flex-1 overflow-y-auto p-4 sm:p-8 flex justify-center bg-neutral-200/60 dark:bg-neutral-950/80 scrollbar-thin">
          <div className={`w-full ${getCanvasWidthClass()} flex flex-col bg-neutral-900 border border-neutral-800 rounded-3xl shadow-2xl overflow-hidden transition-all duration-300 self-start`}>
            {/* Simulated Storefront Navbar */}
            <div className="h-14 px-6 bg-neutral-950 border-b border-neutral-800 flex items-center justify-between text-neutral-300 text-xs">
              <div className="flex items-center gap-4">
                <div className="flex items-center gap-1.5 text-white font-black">
                  <Zap className="w-4 h-4 text-indigo-500" />
                  <span>AURORA</span>
                </div>
                <div className="hidden sm:flex items-center gap-4 font-medium text-neutral-400">
                  <span>Categories</span>
                  <span>Audio</span>
                  <span className="text-indigo-400 font-bold">/{currentPage.slug}</span>
                </div>
              </div>
              <span className="text-[10px] px-2 py-0.5 rounded bg-neutral-800 font-mono text-neutral-400">
                LIVE STOREFRONT SIMULATOR
              </span>
            </div>

            {/* Sections Canvas */}
            <div className="divide-y divide-white/5">
              {adminRuntimePayload && (
                <PageRuntimeProvider payload={adminRuntimePayload}>
                  {currentPage.sections.map(section => (
                    <SectionRenderer
                      key={section.id}
                      section={section}
                      isEditingMode={true}
                      isSelected={activeSectionId === section.id}
                      onSelect={() => setActiveSectionId(section.id)}
                      viewport={viewport}
                    />
                  ))}
                </PageRuntimeProvider>
              )}
            </div>

            {/* Simulated Storefront Footer */}
            <div className="p-8 bg-black border-t border-neutral-800 text-neutral-500 text-xs text-center">
              <p className="font-semibold text-neutral-400 mb-1">AURORA UNIVERSAL ECOMMERCE</p>
              <p className="text-[11px]">All changes sync live to customer storefront routes.</p>
            </div>
          </div>
        </div>

        {/* Right Panel: Section Inspector (Collapsible) */}
        {!isInspectorCollapsed ? (
          <div className="w-84 lg:w-96 flex-shrink-0 flex flex-col transition-all duration-300">
            <SectionInspector
              key={`${currentPage.id}:${activeSection?.id ?? 'none'}`}
              pageId={currentPage.id}
              section={activeSection}
              onChange={handleUpdateSection}
              onMediaUpload={(pageId, sectionId, updater) => {
                void updatePageSection(pageId, sectionId, updater)
                  .then(updated => {
                    if (updated) schedulePage(updated);
                  });
              }}
              onDelete={requestDeleteSection}
              onClose={() => setActiveSectionId(null)}
              onToggleCollapse={() => setIsInspectorCollapsed(true)}
            />
          </div>
        ) : (
          /* Collapsed Section Inspector Rail */
          <div className="w-12 flex-shrink-0 flex flex-col items-center justify-between py-3 border-l border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 transition-all duration-300 z-10 select-none">
            <div className="flex flex-col items-center gap-3">
              <button
                onClick={() => setIsInspectorCollapsed(false)}
                className="p-2 rounded-xl text-neutral-500 dark:text-neutral-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/80 transition-all"
                title="Expand Section Inspector"
              >
                <PanelRightOpen className="w-4 h-4" />
              </button>
              <div
                onClick={() => setIsInspectorCollapsed(false)}
                className="[writing-mode:vertical-lr] rotate-180 py-4 flex items-center gap-2 text-[11px] font-bold tracking-wider text-neutral-400 hover:text-indigo-500 uppercase cursor-pointer transition-colors"
                title="Click to expand inspector"
              >
                <Sliders className="w-3.5 h-3.5 text-indigo-500" />
                <span>Inspector</span>
              </div>
            </div>

            {activeSection && (
              <div className="flex flex-col items-center gap-1" title={`Editing: ${activeSection.title || activeSection.type}`}>
                <span className="w-2.5 h-2.5 rounded-full bg-indigo-500 ring-2 ring-indigo-300 dark:ring-indigo-800 animate-pulse" />
              </div>
            )}
          </div>
        )}
      </div>

      {/* Delete CMS Page Confirmation Modal */}
      <DeleteConfirmationModal
        isOpen={!!pageToDelete}
        itemType="CMS Page"
        itemName={pageToDelete?.title}
        itemDetails={`Path: /pages/${pageToDelete?.slug} • Template: ${pageToDelete?.template} • Sections: ${pageToDelete?.sections.length || 0} blocks`}
        warningMessage={`Deleting "${pageToDelete?.title}" removes its editable document and published snapshot.`}
        impactItems={[
          'The page document and all of its configured sections will be removed.',
          'Any links pointing to this storefront path may become unavailable.',
          'This operation cannot be automatically reversed.',
        ]}
        confirmButtonText="Delete CMS Page"
        onConfirm={() => {
          if (pageToDelete) {
            deletePage(pageToDelete.id);
            setPageToDelete(null);
          }
        }}
        onClose={() => setPageToDelete(null)}
      />

      {/* Delete CMS Page Section Confirmation Modal */}
      <DeleteConfirmationModal
        isOpen={!!sectionToDelete}
        itemType="Page Section Block"
        itemName={sectionToDelete?.title}
        itemDetails={`Block Type: ${sectionToDelete?.type} • Target ID: #${sectionToDelete?.id}`}
        warningMessage={`Removing "${sectionToDelete?.title}" deletes it from this page draft when autosave completes.`}
        impactItems={[
          'This section will no longer appear in page Preview or the published storefront after the next publish.',
          'Its section-specific content and configuration will be removed from this draft.',
        ]}
        confirmButtonText="Remove Section Block"
        onConfirm={() => {
          if (sectionToDelete) {
            handleDeleteSection(sectionToDelete.id);
            setSectionToDelete(null);
          }
        }}
        onClose={() => setSectionToDelete(null)}
      />

      {/* Add Section Template Modal */}
      <AddSectionModal
        isOpen={isAddSectionOpen}
        onClose={() => setIsAddSectionOpen(false)}
        onAdd={handleAddSection}
        categories={categories}
        collections={collections}
      />

      {/* Full Live Preview Modal */}
      <PagePreviewModal
        isOpen={isPreviewModalOpen}
        onClose={() => setIsPreviewModalOpen(false)}
        page={currentPage}
      />

      {/* JSON Schema Exporter/Importer Modal */}
      {isJsonModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs animate-in fade-in">
          <div className="w-full max-w-2xl rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-2xl p-6 flex flex-col">
            <h3 className="text-base font-bold text-neutral-900 dark:text-white mb-2">
              Page Layout JSON Schema
            </h3>
            <p className="text-xs text-neutral-500 dark:text-neutral-400 mb-4">
              Export this JSON template to backup or paste a customized JSON schema to import.
            </p>
            <textarea
              rows={14}
              value={jsonText}
              onChange={e => setJsonText(e.target.value)}
              className="w-full p-4 rounded-xl font-mono text-xs border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-950 text-neutral-900 dark:text-neutral-200 leading-relaxed mb-4 focus:ring-2 focus:ring-indigo-500"
            />
            <div className="flex items-center justify-end gap-2">
              <button
                onClick={() => setIsJsonModalOpen(false)}
                className="px-4 py-2 text-xs font-semibold rounded-xl bg-neutral-200 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300"
              >
                Cancel
              </button>
              <button
                onClick={handleImportJson}
                className="px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 text-white hover:bg-indigo-500"
              >
                Apply / Import Layout
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

import {PageHistoryPanel} from './PageHistoryPanel';
import {History as PageHistoryIcon} from 'lucide-react';
import {AiPageGenerator} from './AiPageGenerator';
import {pageSourceSignature} from '../../cms/cmsAiGenerator';
import {BuilderShellHeader} from '../menus/BuilderShellHeader';
import {ShellDesignPreview} from '../menus/ShellDesignEditor';
import {resolveHeaderPresentation} from '../../cms/headerPresentation';
import {PageStyleSettings} from './PageStyleSettings';
import {createPortal} from 'react-dom';
import {duplicateSection} from '../../cms/duplicateSection';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { SectionMotionPreset, CmsPage, PageDocument, PageDraftPayload, PageSection } from '../../types';
import { useAdmin } from '../../context/AdminContext';
import { useAdminCurrency } from '../../context/useAdminCurrency';
import { readAdminBootstrap } from '../../integration/adminBootstrap';
import {
  archivePage,
  selectHomepage,
  fetchPageDraft,
  publishPage,
  savePageDraft,
  unpublishPage,
} from '../../integration/pageData';
import { PageDraftAutosave, PageDraftAutosaveState } from '../../cms/pageDraftAutosave';
import { hasOwnContractField } from '../../cms/pageContract';
import { repairLegacyCollectionTabs } from '../../cms/collectionTabMappings';
import { createAdminPageRuntimePayload, PageRuntimeProvider } from '../../cms/PageRuntimeContext';
import {pruneImageSlots} from '../../cms/imageTargets';
import { SectionRenderer } from './SectionRenderer';
import { SectionInspector } from './SectionInspector';
import { AddSectionModal } from './AddSectionModal';
import { PageTemplateModal, pageTemplates } from './PageTemplateModal';
import { createPageTemplateStyle, createPageTemplateSections, type PageTemplateContext } from '../../cms/pageTemplates';
import { loadShopBranding } from '../../integration/shopBrandingData';
import {fetchHighlightCoupons} from '../../integration/couponData';
import {couponHighlight,type PromotionOffer} from '../../cms/promotionHighlights';
import { fetchCommerceSettings } from '../../integration/commerceSettingsData';
import { currencyFor } from '../../integration/adminMoney';
import { PagePreviewModal } from './PagePreviewModal';
import { DeleteConfirmationModal } from '../common/DeleteConfirmationModal';
import {
  Play,
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
  Minimize2,
  Sun,
  Moon,
  Home,
  Check
} from 'lucide-react';

type PageAutosaveTakeover = {
  controller: PageDraftAutosave<PageDocument> | null;
  pendingDocument: PageDocument | null;
  navigationIntent: number;
  openPreview: boolean;
};

export const PageAdmin: React.FC = () => {
  const [aiGeneratorOpen,setAiGeneratorOpen]=useState(false);
  const [templateOpen,setTemplateOpen]=useState(false),[templateBusy,setTemplateBusy]=useState(false);
  const [deliveryRates,setDeliveryRates]=useState<PageTemplateContext['shippingRates']>(undefined);
  const [templateContext,setTemplateContext]=useState<PageTemplateContext>({});
  const [templateSettingsState,setTemplateSettingsState]=useState<'loading'|'ready'|'error'>('loading');
  const templateLoadRequest=useRef(0);
  useEffect(()=>()=>{templateLoadRequest.current++;},[]);
  const {
    pages,
    theme,
    toggleTheme,
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
    storefrontShellSettings,
    headerMenus,
    selectedPageId,
    setSelectedPageId,
    addToast
  } = useAdmin();
  const { currencyCode } = useAdminCurrency();

  const [motionReplayCounter, setMotionReplayCounter] = useState(0);
  const handleReplayMotion = () => setMotionReplayCounter(value => value + 1);
  const handleApplyMotionToAll = (preset: SectionMotionPreset) => {
    if (!currentPage) return;
    const updated = updatePageLocal(currentPage.id, latest => ({ ...latest, sections: latest.sections.map(section => ({ ...section, motionPreset: preset })) }));
    if (updated) {
      schedulePage(updated);
      handleReplayMotion();
      addToast({ type: 'success', title: 'Motion Applied', message: 'Motion applied to all sections.' });
    }
  };

  // Mode state: null = Page Directory, non-null = Editing that Page ID
  const [historyOpen,setHistoryOpen]=useState(false);
  const aiHistorySource=useRef(false);
  const [editingPageId, setEditingPageId] = useState<string | null>(selectedPageId || null);
  const [selectedElement,setSelectedElement]=useState<{sectionId:string;target:string;role?:string;revision:number}|null>(null);
  const selectElement=(sectionId:string,target:string,role?:string)=>{setActiveSectionId(sectionId);setIsInspectorCollapsed(false);setSelectedElement(previous=>({sectionId,target,role,revision:(previous?.revision??0)+1}));};
  const [promotionOffers,setPromotionOffers]=useState<PromotionOffer[]>([]);
  const [selectedImage, setSelectedImage] = useState<{sectionId:string;target:string}|null>(null);
    const [activeSectionId, setActiveSectionId] = useState<string | null>(null);
  const [isAddSectionOpen, setIsAddSectionOpen] = useState(false);
  const [isPreviewModalOpen, setIsPreviewModalOpen] = useState(false);
  const [isOutlineCollapsed, setIsOutlineCollapsed] = useState(false);
  const [isInspectorCollapsed, setIsInspectorCollapsed] = useState(false);
  const [viewport, setViewport] = useState<'desktop' | 'tablet' | 'mobile'>('desktop');
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<'all' | 'published' | 'draft' | 'archived'>('all');
  const canvasScrollRef=useRef<HTMLDivElement>(null);
  const outlineScrollRequest=useRef(0);
  const selectOutlineSection=(sectionId:string)=>{
    setActiveSectionId(sectionId);
    setIsInspectorCollapsed(false);
    setPageSettingsOpen(false);
    const request=++outlineScrollRequest.current;
    requestAnimationFrame(async()=>{
      const canvas=canvasScrollRef.current;
      const target=document.getElementById(`section-${sectionId}`);
      if(!canvas||!target||!canvas.contains(target))return;
      const resizing=canvas.firstElementChild?.getAnimations().map(animation=>animation.finished.catch(()=>undefined))??[];
      if(resizing.length)await Promise.all(resizing);
      if(request!==outlineScrollRequest.current||!canvas.isConnected||!target.isConnected)return;
      const header=canvas.querySelector<HTMLElement>('[data-builder-shell]');
      const stickyOffset=header?header.getBoundingClientRect().height:0;
      const top=canvas.scrollTop+target.getBoundingClientRect().top-canvas.getBoundingClientRect().top-stickyOffset-12;
      canvas.scrollTo({top:Math.max(0,top),behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});
    });
  };
  const [toolbarMoreOpen,setToolbarMoreOpen] = useState(false);
  const [pageSettingsOpen,setPageSettingsOpen]=useState(false);
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
  const activeHomepage = pages.find(p => p.isHomepage);
  const homepageSwitchPending = useRef(false);
  const setPageAsHomepage = async (id: string) => {
    if (homepageSwitchPending.current) return;
    homepageSwitchPending.current = true;
    try {
      if (!await flushCurrentPage()) return;
      const version = id === editingPageId && autosaveRef.current
        ? autosaveRef.current.getState().acknowledgedVersion
        : pages.find(page => page.id === id)?.lockVersion;
      if (version === undefined) throw new Error('Reload this page before selecting its homepage.');
      const saved = await selectHomepage(laravelBootstrap, id, version);
      pages.forEach(page => updatePageLocal(page.id, latest => ({ ...latest, isHomepage: page.id === saved.id })));
      updatePageLocal(id, latest => ({ ...latest, status: saved.status, hasPublishedSnapshot: saved.hasPublishedSnapshot }));
      addToast({ type: 'success', title: 'Homepage Updated', message: `${saved.title} is now the storefront homepage.` });
    } catch (error) { reportSaveFailure(error); }
    finally { homepageSwitchPending.current = false; }
  };
  useEffect(()=>{if(!currentPage?.sections.some(s=>(s.type==='delivery_table'||s.type==='chapter_block'&&s.chapterPreset==='delivery')))return;let active=true;const load=()=>fetchCommerceSettings(laravelBootstrap).then(settings=>{if(active)setDeliveryRates(settings.shippingRates);}).catch(()=>{if(active)setDeliveryRates(undefined);});void load();window.addEventListener('focus',load);return()=>{active=false;window.removeEventListener('focus',load);};},[currentPage?.id,Boolean(currentPage?.sections.some(s=>(s.type==='delivery_table'||s.type==='chapter_block'&&s.chapterPreset==='delivery')))]);
  useEffect(()=>{if(!currentPage?.sections.some(s=>s.type==='promotion_highlights'))return;let active=true;
   const load=()=>fetchHighlightCoupons(laravelBootstrap).then(data=>{if(active)setPromotionOffers(data.map(c=>couponHighlight(c)));}).catch(()=>{if(active)setPromotionOffers([]);});
   void load();window.addEventListener('focus',load);const timer=window.setInterval(load,60000);
   return()=>{active=false;window.removeEventListener('focus',load);window.clearInterval(timer);};
  },[currentPage?.id,Boolean(currentPage?.sections.some(s=>s.type==='promotion_highlights'))]);
  const activeSection = currentPage?.sections.find(s => s.id === activeSectionId) || null;
  const adminRuntimePayload = useMemo(() => currentPage
    ? createAdminPageRuntimePayload(currentPage, { products, categories, collections }, currencyCode,deliveryRates??templateContext.shippingRates,promotionOffers)
    : null,
  [currentPage, products, categories, collections, currencyCode,templateContext.shippingRates,deliveryRates,promotionOffers]);

  const toPageDocument = (page: CmsPage): PageDocument => ({
    title: page.title,
    slug: page.slug,
    template: page.template,
    ...(page.shellDesign?{shellDesign:page.shellDesign}:{}),
    ...(page.headerPresentation?{headerPresentation:page.headerPresentation}:{}),
    ...(page.containerWidth?{containerWidth:page.containerWidth}:{}),
    ...(page.style?{style:page.style}:{}),
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
      shellDesign: _shellDesign,
      headerPresentation: _headerPresentation,
      containerWidth: _containerWidth,
      style: _style,
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
            undefined, aiHistorySource.current ? 'ai_apply' : undefined,
          );
          aiHistorySource.current=false;
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
        setSelectedPageId(page.id);
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

  const handleOpenTemplates = async () => {
    if (templateBusy || templateOpen) return;
    const request=++templateLoadRequest.current;
    setTemplateContext({});
    setTemplateSettingsState('loading');
    setTemplateOpen(true);
    try {
      // Read persisted facts directly; AdminContext starts with demonstration defaults.
      const [profile,commerce] = await Promise.all([loadShopBranding(),fetchCommerceSettings(laravelBootstrap)]);
      if(request!==templateLoadRequest.current)return;
      setTemplateContext({shopName:profile.shopName,tagline:profile.tagline,
        currency:currencyFor(commerce.currencies)?.code || profile.currency,shippingRates:commerce.shippingRates});
      setTemplateSettingsState('ready');
    } catch {
      if(request!==templateLoadRequest.current)return;
      setTemplateSettingsState('error');
    }
  };
  const handleCloseTemplates = () => {
    if(templateBusy)return;
    templateLoadRequest.current++;
    setTemplateOpen(false);
  };
  // Handler for creating a brand new page
  const handleCreateNewPage = async (templateId: string) => {
    const template = pageTemplates.find(t=>t.id===templateId && t.ready);
    if (!template || templateBusy || (templateId!=='blank' && templateSettingsState!=='ready')) return;
    setTemplateBusy(true);
    try {
      const stamp = crypto.randomUUID();
      const newPage: CmsPage = {
        id: 'page-' + stamp, title: template.name === 'Blank Page' ? 'New Page' : template.name,
        slug: template.id + '-' + stamp.slice(0,8), description: template.description,
        status:'draft', template:'standard', createdAt:new Date().toISOString().split('T')[0], updatedAt:new Date().toISOString().split('T')[0],
        ...(['about-editorial','about-studio','about-chapters'].includes(templateId) ? {containerWidth:'standard' as const} : {}),
        style:createPageTemplateStyle(templateId),
    ...(templateId==='wedding-photography'?{containerWidth:'site' as const}:{}),
        ...(templateId==='wedding-photography'?{shellDesign:{business:'service' as const,style:'editorial' as const,appearance:'light' as const,showAnnouncement:false,showAccount:false,showCurrency:false,showThemeSwitch:false,ctaLabel:'',ctaUrl:'',tagline:'',contactEmail:''}}:{}),
        ...(templateId==='beauty-skincare'?{headerPresentation:{mode:'translucent' as const,tone:'dark' as const}}:{}),
        ...(templateId==='everyday-market'?{containerWidth:'wide' as const}:{}),
        sections:createPageTemplateSections(template.id, {...templateContext,products,categories,collections,promotionOffers}, 'sec-'+stamp)
      };
      const saved = await savePage(newPage);
      if (saved) { templateLoadRequest.current++; setTemplateOpen(false); await hydrateEditor(saved); }
    } finally {setTemplateBusy(false);}
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
      updatedSection = pruneImageSlots(updatedSection);
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
    const duplicated = duplicateSection(target);
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


        {templateOpen && <PageTemplateModal context={{...templateContext,products,categories,collections,promotionOffers}} settingsState={templateSettingsState} busy={templateBusy} onClose={handleCloseTemplates} onCreate={handleCreateNewPage}/>}
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
              onClick={()=>{void handleOpenTemplates();}}
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

        {/* Designated Storefront Homepage (index.php) Routing Hub */}
        <div className="p-4 sm:p-5 rounded-3xl bg-gradient-to-r from-neutral-900 via-indigo-950/50 to-neutral-900 border-2 border-indigo-500/40 shadow-xl shadow-indigo-500/5 relative overflow-hidden">
          <div className="absolute right-0 top-0 bottom-0 w-96 bg-gradient-to-l from-indigo-600/10 to-transparent pointer-events-none" />
          <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 relative z-10">
            <div className="flex items-start sm:items-center gap-3.5">
              <div className="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-600 text-white flex items-center justify-center shrink-0 shadow-lg shadow-indigo-600/30">
                <Home className="w-6 h-6" />
              </div>
              <div>
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="text-[11px] font-black uppercase tracking-wider text-indigo-400">
                    Storefront Active Homepage
                  </span>
                  <span className="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 flex items-center gap-1.5">
                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
                    Route: / (index.php)
                  </span>
                  <span className="text-[10px] text-neutral-400 bg-neutral-800/80 px-2 py-0.5 rounded-md border border-neutral-700/60">
                    Single-Page Exclusive Routing
                  </span>
                </div>
                <p className="text-base font-extrabold text-white mt-1 flex items-center gap-2 flex-wrap">
                  {activeHomepage ? activeHomepage.title : 'No Homepage Designated'}
                  {activeHomepage && (
                    <span className="text-xs font-normal text-neutral-300">
                      • {activeHomepage.sections.length} modular blocks • {activeHomepage.template} template
                    </span>
                  )}
                </p>
              </div>
            </div>

            <div className="flex items-center gap-2.5 flex-wrap sm:flex-nowrap shrink-0">
              {/* Quick Switch Homepage Dropdown */}
              <div className="flex items-center gap-2 bg-neutral-950/80 border border-neutral-700/80 rounded-xl px-3 py-1.5 shadow-inner">
                <span className="text-xs text-neutral-400 whitespace-nowrap font-medium">Switch Home:</span>
                <select
                  value={activeHomepage?.id || ''}
                  onChange={(e) => {
                    if (e.target.value) {
                      setPageAsHomepage(e.target.value);
                    }
                  }}
                  className="bg-transparent text-xs font-bold text-indigo-300 focus:outline-none cursor-pointer pr-1"
                >
                  {!activeHomepage && <option value="" disabled className="bg-neutral-900 text-white">Select Homepage</option>}
                  {pages.map((p) => (
                    <option key={p.id} value={p.id} className="bg-neutral-900 text-white">
                      {p.title} {p.status === 'draft' ? '(Draft → Auto-Publish)' : ''} {p.isHomepage ? '★ (Active)' : ''}
                    </option>
                  ))}
                </select>
              </div>

              {activeHomepage && (
                <>
                  <button
                    onClick={() => {
                      window.open('/', '_blank', 'noopener,noreferrer');
                    }}
                    className="px-3.5 py-2 rounded-xl text-xs font-bold bg-white/10 hover:bg-white/20 text-white transition-colors flex items-center gap-1.5 cursor-pointer"
                    title="Live preview customer view of index.php"
                  >
                    <Eye className="w-3.5 h-3.5" />
                    <span>Preview index.php</span>
                  </button>
                  <button
                    onClick={() => void hydrateEditor(activeHomepage)}
                    className="px-3.5 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white transition-all shadow-md shadow-indigo-600/30 flex items-center gap-1.5 cursor-pointer"
                  >
                    <Edit3 className="w-3.5 h-3.5" />
                    <span>Edit Layout</span>
                  </button>
                </>
              )}
            </div>
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
              className={`rounded-3xl bg-white dark:bg-neutral-900 border shadow-xs hover:shadow-xl transition-all duration-300 flex flex-col overflow-hidden group relative ${
                page.isHomepage
                  ? 'border-indigo-500/80 ring-2 ring-indigo-500 dark:ring-indigo-400/90 shadow-lg shadow-indigo-500/10'
                  : 'border-neutral-200 dark:border-neutral-800 hover:border-indigo-500/40'
              }`}
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

                {/* Status Badges & Homepage Badge */}
                <div className="absolute top-3 left-3 right-3 flex items-center justify-between gap-2">
                  <div className="flex items-center gap-1.5 flex-wrap">
                    {page.isHomepage && (
                      <span className="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-md border border-indigo-300/30">
                        <Home className="w-3 h-3" />
                        Active Home (index.php)
                      </span>
                    )}
                    <span className={`text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full backdrop-blur-md shadow-md ${
                      page.status === 'published'
                        ? 'bg-emerald-500/90 text-white'
                        : page.status === 'draft'
                        ? 'bg-amber-500/90 text-white'
                        : 'bg-neutral-800/90 text-neutral-300'
                    }`}>
                      {page.status}
                    </span>
                  </div>
                  <span className="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-black/60 text-neutral-300 backdrop-blur-md border border-white/10 shrink-0">
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

                  {page.isHomepage ? (
                    <div className="flex items-center gap-1.5 mb-2 flex-wrap">
                      <span className="text-xs font-mono font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/70 px-2 py-0.5 rounded-md border border-indigo-200 dark:border-indigo-800/80 flex items-center gap-1">
                        <Home className="w-3 h-3" />
                        / (index.php)
                      </span>
                      <span className="text-[11px] font-mono text-neutral-400">
                        slug: /pages/{page.slug}
                      </span>
                    </div>
                  ) : (
                    <p className="text-xs font-mono text-neutral-400 dark:text-neutral-500 mb-2">
                      /pages/{page.slug}
                    </p>
                  )}

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
                <div className="pt-4 border-t border-neutral-100 dark:border-neutral-800/80 flex flex-col gap-3">
                  <div className="flex items-center justify-between gap-2">
                    {/* Homepage Action or Badge */}
                    {page.isHomepage ? (
                      <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/80">
                        <Check className="w-3.5 h-3.5" />
                        <span>Primary Home</span>
                      </span>
                    ) : (
                      <button
                        type="button"
                        onClick={() => setPageAsHomepage(page.id)}
                        className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold text-neutral-600 dark:text-neutral-300 hover:text-indigo-600 dark:hover:text-indigo-400 bg-neutral-100 dark:bg-neutral-800 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 border border-neutral-200 dark:border-neutral-700/80 transition-all cursor-pointer group/btn"
                        title="Set this layout as the primary Storefront Home Page (index.php)"
                      >
                        <Home className="w-3.5 h-3.5 text-neutral-400 group-hover/btn:text-indigo-500 transition-colors" />
                        <span>Set as Home</span>
                      </button>
                    )}

                    <span className="text-[10px] text-neutral-400 font-medium">
                      Updated {page.updatedAt}
                    </span>
                  </div>

                  <div className="flex items-center justify-end gap-1.5">
                    <button
                      onClick={() => {
                        void hydrateEditor(page, true);
                      }}
                      className="p-2 rounded-xl text-neutral-500 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
                      title="Preview Live Page"
                    >
                      <Eye className="w-4 h-4" />
                    </button>
                    <button
                      onClick={() => duplicatePage(page.id)}
                      className="p-2 rounded-xl text-neutral-500 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
                      title="Clone Page Layout"
                    >
                      <Copy className="w-4 h-4" />
                    </button>
                    <button
                      onClick={() => {
                        if (page.isHomepage) {
                          addToast({
                            type: 'warning',
                            title: 'Action Prevented',
                            message: 'Cannot delete the active Storefront Homepage (index.php). Please designate another page as Homepage before deleting this page.'
                          });
                          return;
                        }
                        setPageToDelete(page);
                      }}
                      disabled={page.isHomepage}
                      className={`p-2 rounded-xl transition-colors ${
                        page.isHomepage
                          ? 'text-neutral-300 dark:text-neutral-700 cursor-not-allowed opacity-40'
                          : 'text-neutral-500 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/30 cursor-pointer'
                      }`}
                      title={page.isHomepage ? 'Active Homepage cannot be deleted' : 'Delete Page'}
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                    <button
                      onClick={() => {
                        void hydrateEditor(page);
                      }}
                      className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl font-bold text-xs bg-indigo-600 text-white hover:bg-indigo-500 transition-colors shadow-xs cursor-pointer"
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
      default: return 'max-w-[1280px] w-full';
    }
  };

  return (
    <div id="page-visual-builder" inert={aiGeneratorOpen||undefined} className={'w-full h-full flex flex-col bg-neutral-100 dark:bg-neutral-950 overflow-hidden animate-in fade-in'}>
      {aiGeneratorOpen&&createPortal(<AiPageGenerator page={currentPage} selected={activeSectionId} onClose={()=>setAiGeneratorOpen(false)} onApply={async(document,create,signature)=>{
        if(!create&&pageSourceSignature(currentPage)!==signature)throw new Error('The source page changed while AI was open. Reopen the generator from the latest draft.');
        if(!await flushCurrentPage())throw new Error('Save the current page before applying AI changes.');
        if(create){const saved=await savePage({...currentPage,...document,id:crypto.randomUUID(),status:'draft',isHomepage:false,lockVersion:undefined},'ai_apply');if(!saved)throw new Error('The new draft could not be saved.');setEditingPageId(saved.id);setSelectedPageId(saved.id);}
        else{aiHistorySource.current=true;const updated=updatePageLocal(currentPage.id,latest=>({...latest,...document}));if(!updated)throw new Error('Page is unavailable.');schedulePage(updated);if(!await flushCurrentPage())throw new Error('The draft could not be saved.');}
        setAiGeneratorOpen(false);addToast({type:'success',title:'AI Draft Ready',message:'Editable content saved to draft. The published page is unchanged.'});
      }}/>,document.body)}
      {/* Builder toolbar: page, canvas and publishing actions */}
      <div inert={historyOpen||undefined} data-cms-builder-toolbar className="min-h-16 px-4 py-2 border-b border-neutral-200 dark:border-neutral-800 bg-white/95 dark:bg-neutral-900/95 backdrop-blur-md flex flex-wrap items-center gap-3 z-20 shrink-0 shadow-2xs">
        <div className="flex items-center gap-2 min-w-0 flex-1">
          <button type="button" aria-label="Exit to Pages" title="Back to Pages" onClick={()=>{void handleBackToDirectory();}} className="w-9 h-9 shrink-0 grid place-items-center rounded-xl text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800"><ChevronLeft className="w-5 h-5"/></button>
          <input aria-label="Page title" value={currentPage.title} onChange={e=>handleTitleChange(e.target.value)} className="min-w-0 w-full max-w-64 font-bold text-sm text-neutral-900 dark:text-white bg-transparent border-b border-transparent focus:border-indigo-500 focus:outline-hidden py-1 truncate"/>
          {currentPage.isHomepage&&<Home aria-label="Active Home" className="w-4 h-4 shrink-0 text-indigo-500"/>}
          <button type="button" aria-label="Page Settings" title="Page Settings" aria-expanded={pageSettingsOpen} onClick={()=>setPageSettingsOpen(!pageSettingsOpen)} className="w-9 h-9 shrink-0 grid place-items-center rounded-xl text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"><SlidersHorizontal className="w-4 h-4"/></button>
          {pageSettingsOpen&&createPortal(<PageStyleSettings page={currentPage} onClose={()=>setPageSettingsOpen(false)} onChange={patch=>{const updated=updatePageLocal(currentPage.id,latest=>({...latest,...patch}));if(updated)schedulePage(updated);}}/>,document.body)}
        </div>
        <div role="group" aria-label="Canvas view" className="flex items-center gap-1 p-1 rounded-xl bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 shrink-0">
          {([{id:'desktop',label:'Desktop',hint:'Desktop 1280px',Icon:Monitor},{id:'tablet',label:'Tablet',hint:'Tablet 768px',Icon:Tablet},{id:'mobile',label:'Mobile',hint:'Mobile 390px',Icon:Smartphone}] as const).map(({id,label,hint,Icon})=><button type="button" key={id} aria-label={label} title={hint} aria-pressed={viewport===id} onClick={()=>setViewport(id)} className={`w-8 h-8 grid place-items-center rounded-lg transition-colors ${viewport===id?'bg-white dark:bg-neutral-900 text-indigo-600 dark:text-indigo-400 shadow-xs':'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'}`}><Icon className="w-4 h-4"/></button>)}
          <span className="w-px h-4 bg-neutral-200 dark:bg-neutral-700 mx-1"/>
          <button type="button" aria-label="Full Canvas" title={isOutlineCollapsed&&isInspectorCollapsed?'Restore panels':'Full Canvas'} aria-pressed={isOutlineCollapsed&&isInspectorCollapsed} onClick={()=>{const collapse=!(isOutlineCollapsed&&isInspectorCollapsed);setIsOutlineCollapsed(collapse);setIsInspectorCollapsed(collapse);}} className={`w-8 h-8 grid place-items-center rounded-lg ${isOutlineCollapsed&&isInspectorCollapsed?'bg-indigo-600 text-white':'text-neutral-500 hover:bg-neutral-200 dark:hover:bg-neutral-700'}`}>{isOutlineCollapsed&&isInspectorCollapsed?<Minimize2 className="w-4 h-4"/>:<Maximize2 className="w-4 h-4"/>}</button>
        </div>
        <div className="flex items-center gap-2 shrink-0 ml-auto">
          <details open={toolbarMoreOpen} className="relative" onKeyDown={e=>{if(e.key==='Escape')setToolbarMoreOpen(false);}}>
            <summary aria-label="More actions" aria-expanded={toolbarMoreOpen} onClick={e=>{e.preventDefault();setToolbarMoreOpen(open=>!open);}} title="More actions" className="list-none w-9 h-9 grid place-items-center rounded-xl cursor-pointer text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 [&::-webkit-details-marker]:hidden"><MoreVertical className="w-4 h-4"/></summary>
            <div className="absolute right-0 top-full mt-2 w-52 p-1.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shadow-xl text-neutral-700 dark:text-neutral-200" onClick={e=>{if((e.target as HTMLElement).closest('button'))setToolbarMoreOpen(false);}}>
              <button type="button" onClick={()=>setAiGeneratorOpen(true)} className="w-full flex items-center gap-2 p-2.5 rounded-lg text-xs text-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-950"><Sparkles className="w-4 h-4"/>AI Page Generator</button>
              <button type="button" onClick={handleReplayMotion} className="w-full flex items-center gap-2 p-2.5 rounded-lg text-xs hover:bg-neutral-100 dark:hover:bg-neutral-800"><Play className="w-4 h-4"/>Replay Motion</button>
              <button type="button" onClick={toggleTheme} className="w-full flex items-center gap-2 p-2.5 rounded-lg text-xs hover:bg-neutral-100 dark:hover:bg-neutral-800">{theme==='dark'?<Sun className="w-4 h-4"/>:<Moon className="w-4 h-4"/>}{theme==='dark'?'Switch to Light Mode':'Switch to Dark Mode'}</button>
              <button type="button" disabled={currentPage.isHomepage} onClick={()=>setPageAsHomepage(currentPage.id)} className="w-full flex items-center gap-2 p-2.5 rounded-lg text-xs hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-50"><Home className="w-4 h-4"/>{currentPage.isHomepage?'Active Home':'Set as Home'}</button>
            </div>
          </details>
          <button type="button" aria-label="Page history" onClick={()=>{void flushCurrentPage().then(ok=>{if(ok)setHistoryOpen(true);});}} className="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-neutral-700 dark:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800"><PageHistoryIcon className="w-4 h-4"/><span>History</span></button>
          <button type="button" aria-label="Preview" onClick={()=>{void handlePreview();}} className="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-neutral-700 dark:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800"><Eye className="w-4 h-4"/><span className="hidden sm:inline">Preview</span></button>
          {/* Status selector */}
          <select
            aria-label="Page status"
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


          <button type="button" onClick={()=>setIsAddSectionOpen(true)} className="flex items-center gap-1.5 px-3.5 py-2 rounded-xl font-bold text-xs bg-indigo-600 text-white hover:bg-indigo-500 shadow-xs whitespace-nowrap"><Plus className="w-4 h-4"/><span>Add Section</span></button>
        </div>
      </div>

      {historyOpen&&<PageHistoryPanel page={currentPage} onClose={()=>setHistoryOpen(false)} onRestored={draft=>{replacePageLocal(pageFromDraft(currentPage,draft));adoptAutosave(currentPage.id,makeAutosave(currentPage.id,draft.lockVersion));setActiveSectionId(draft.document.sections[0]?.id??null);}} onReload={async()=>{await hydrateEditor(currentPage);}}/>}
      {/* 2. Main 3-Column Studio Workspace */}
      <div style={{display:historyOpen?'none':undefined}} className="relative isolate flex-1 flex min-h-0 overflow-hidden">
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
                    onClick={() => selectOutlineSection(section.id)}
                    tabIndex={0}
                    role="button"
                    aria-label={`Go to section ${idx+1}: ${section.title||'Untitled Section'}`}
                    onKeyDown={e=>{if(e.target===e.currentTarget&&(e.key==='Enter'||e.key===' ')){e.preventDefault();selectOutlineSection(section.id);}}}
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
        <div ref={canvasScrollRef} data-cms-canvas-scroll className="flex-1 overflow-y-auto p-4 sm:p-8 flex justify-center bg-neutral-200/60 dark:bg-neutral-950/80 scrollbar-thin">
          <div className={`w-full ${getCanvasWidthClass()} flex flex-col bg-neutral-900 border border-neutral-800 rounded-3xl shadow-2xl overflow-hidden transition-all duration-300 self-start`}>
            {/* Simulated Storefront Navbar */}
            {(storefrontShellSettings.shellDesign??currentPage.shellDesign) ? <BuilderShellHeader value={(storefrontShellSettings.shellDesign??currentPage.shellDesign)!} brand={shopSettings.shopName||'JW Studio'} logo={shopSettings.shopLogo} announcement={storefrontShellSettings.enabled?(storefrontShellSettings.zones.center.enabled?storefrontShellSettings.zones.center.text:storefrontShellSettings.zones.left.enabled?storefrontShellSettings.zones.left.text:undefined):undefined} menus={headerMenus} mobile={viewport!=='desktop'} presentation={resolveHeaderPresentation(currentPage.headerPresentation,storefrontShellSettings.headerPresentation,currentPage.sections)}/> : <>            <link rel="stylesheet" href="/css/cms-header-overlay.css"/><div data-builder-header data-mode={resolveHeaderPresentation(currentPage.headerPresentation,storefrontShellSettings.headerPresentation,currentPage.sections).mode} style={viewport==='desktop'&&resolveHeaderPresentation(currentPage.headerPresentation,storefrontShellSettings.headerPresentation,currentPage.sections).mode!=='normal'?{marginBottom:-56,zIndex:40,background:resolveHeaderPresentation(currentPage.headerPresentation,storefrontShellSettings.headerPresentation,currentPage.sections).mode==='translucent'?'rgba(255,255,255,.7)':'transparent',color:resolveHeaderPresentation(currentPage.headerPresentation,storefrontShellSettings.headerPresentation,currentPage.sections).tone==='light'?'#ffffff':'#18181b'}:undefined} className="h-14 px-6 bg-neutral-950 border-b border-neutral-800 flex items-center justify-between text-neutral-300 text-xs">
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

            </>}
            {/* Sections Canvas */}
            <div data-cms-page-header-overlay={viewport==='desktop'&&resolveHeaderPresentation(currentPage.headerPresentation,storefrontShellSettings.headerPresentation,currentPage.sections).mode!=='normal'?'true':undefined} className="w-full">
              {adminRuntimePayload && (
                <PageRuntimeProvider payload={adminRuntimePayload}>
                  {currentPage.sections.map(section => (
                    <SectionRenderer
                      key={`${section.id}-${motionReplayCounter}`}
                      section={section}
                      isEditingMode={true}
                      isSelected={activeSectionId === section.id}
                      onSelect={() => setActiveSectionId(section.id)}
                      selectedImage={selectedImage?.sectionId===section.id?selectedImage.target:undefined}
                      onSelectImage={target=>{selectElement(section.id,'image');setSelectedImage({sectionId:section.id,target});}}
                      onSelectElement={(target,role)=>selectElement(section.id,target,role)}
                      viewport={viewport}
                    />
                  ))}
                </PageRuntimeProvider>
              )}
            </div>

            {/* Simulated Storefront Footer */}
            {(storefrontShellSettings.shellDesign??currentPage.shellDesign) ? <ShellDesignPreview value={(storefrontShellSettings.shellDesign??currentPage.shellDesign)!} brand={shopSettings.shopName||'JW Studio'} footer footerOnly/> : <>            <div className="p-8 bg-black border-t border-neutral-800 text-neutral-500 text-xs text-center">
              <p className="font-semibold text-neutral-400 mb-1">AURORA UNIVERSAL ECOMMERCE</p>
              <p className="text-[11px]">All changes sync live to customer storefront routes.</p>
            </div></>}
          </div>
        </div>

        {/* Right Panel: Section Inspector (Collapsible) */}
        {!isInspectorCollapsed ? (
          <div className="w-84 lg:w-96 flex-shrink-0 flex flex-col transition-all duration-300">
            <SectionInspector pageTypography={currentPage.style?.typography} pagePalette={currentPage.style?.palette} followTheme={currentPage.style?.colorSource==='theme'} promotionOffers={promotionOffers}
                selectedElement={selectedElement?.sectionId===activeSection?.id?selectedElement:undefined}
              key={currentPage.id}
              pageId={currentPage.id}
              section={activeSection}
                selectedImage={selectedImage?.sectionId===activeSection?.id?selectedImage?.target:undefined}
                onSelectImage={target=>activeSection && setSelectedImage({sectionId:activeSection.id,target})}
              onChange={handleUpdateSection}
              onReplayMotion={handleReplayMotion}
              onApplyMotionToAll={handleApplyMotionToAll}
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
        onSetHomepage={setPageAsHomepage}
      />

      {templateOpen && <PageTemplateModal context={{...templateContext,products,categories,collections,promotionOffers}} settingsState={templateSettingsState} busy={templateBusy} onClose={handleCloseTemplates} onCreate={handleCreateNewPage}/>}
    </div>
  );
};

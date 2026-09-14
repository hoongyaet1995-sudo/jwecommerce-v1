import React, { useState, useMemo, useEffect, useRef } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { Product, MarketplacePlatform, PublishListingOptions, PublishAsyncStatus } from '../../types';
import { MarketplaceLogo } from './MarketplaceLogo';
import {useMarketplacePublishingTask} from '../../../../marketplace/useMarketplacePublishingTask';
import {dispatchSavedPublishingTask} from '../../../../marketplace/dispatchSavedPublishingTask';
import {useMarketplaceListingCategories} from '../../../../marketplace/useMarketplaceListingCategories';
import {useMarketplaceListingBrands} from '../../../../marketplace/useMarketplaceListingBrands';
import {readPublishingSource,type PublishingSource} from '../../../../integration/marketplacePublishing';
import {useLazadaPublishingAttributes} from '../../../../marketplace/useLazadaPublishingAttributes';
import {LazadaPublishingAttributes} from '../../../../marketplace/LazadaPublishingAttributes';
import {useShopeePublishingRequirements} from '../../../../marketplace/useShopeePublishingRequirements';
import {ShopeePublishingRequirements} from '../../../../marketplace/ShopeePublishingRequirements';
import {
  X,
  Search,
  UploadCloud,
  Store,
  Layers,
  CheckCircle2,
  AlertCircle,
  AlertTriangle,
  TrendingUp,
  Package,
  ShieldCheck,
  Sparkles,
  ArrowRight,
  RefreshCw,
  Clock,
  ExternalLink,
  Boxes,
  HelpCircle,
  Check,
  ChevronRight,
  Info
} from 'lucide-react';

interface PublishProductModalProps {
  isOpen: boolean;
  onClose: () => void;
  preselectedProductId?: string;
  preselectedConnectionId?: string;
  taskId?: string;
  readOnlyMetadata?: boolean;
  liveData?: boolean;
  submitReviewed?: (productId: string, storeId: string, options: PublishListingOptions) => Promise<string>;
}

type WizardStep = 'destination' | 'attributes' | 'pricing_sync' | 'publishing';

export const PublishProductModal: React.FC<PublishProductModalProps> = ({
  isOpen,
  onClose,
  preselectedProductId,
  preselectedConnectionId,
  taskId: suppliedTaskId,
  readOnlyMetadata = false,
  liveData = false,
  submitReviewed
}) => {
  const {
    products = [],
    connections = [],
    marketplaceStoreProducts = [],
    publishMasterProductToStore,
    addToast,
    setActiveSection
  } = useAdmin();

  // Active step
  const [submittedTaskId, setSubmittedTaskId] = useState<string>();
  const taskId = suppliedTaskId ?? submittedTaskId;
  const submissionStarted = useRef(false);
  const [simulationStep, setCurrentStep] = useState<WizardStep>('destination');
  const monitor = useMarketplacePublishingTask(taskId ?? null,isOpen);
  const [dispatching,setDispatching]=useState(false);
  const executeSaved=async(id:string)=>{
    setDispatching(true);
    try {await dispatchSavedPublishingTask(id);}
    catch(error){addToast({type:'error',title:'Check saved publishing task',message:error instanceof Error?error.message:'Check this task before retrying.'});}
    finally{setDispatching(false);monitor.checkStatus();}
  };
  const currentStep = taskId ? 'publishing' : simulationStep;

  // Step 1: Destination & Product
  const [selectedProductId, setSelectedProductId] = useState<string>(preselectedProductId || '');
  const [selectedConnectionId, setSelectedConnectionId] = useState<string>(
    preselectedConnectionId || connections[0]?.id || ''
  );
  const [searchQuery, setSearchQuery] = useState('');
  const useLiveMetadata=readOnlyMetadata || liveData;
  const [sourceState,setSourceState]=useState<{id:string;source?:PublishingSource;error?:string}|null>(null);
  const source=sourceState?.id===selectedProductId?sourceState.source:undefined;
  useEffect(()=>{
    if (!liveData || !selectedProductId) return;
    let active=true;
    setSourceState({id:selectedProductId});
    readPublishingSource(Number(selectedProductId)).then(value=>{
      if(active)setSourceState({id:selectedProductId,source:value});
    }).catch(error=>{if(active)setSourceState({id:selectedProductId,error:error instanceof Error?error.message:'Product could not be loaded.'});});
    return()=>{active=false;};
  },[liveData,selectedProductId]);

  // Step 2: Category & Marketplace Attributes
  const [selectedCategory, setSelectedCategory] = useState(useLiveMetadata ? '' : 'Consumer Electronics & Audio > Headphones');
  const categoryMetadata = useMarketplaceListingCategories(useLiveMetadata && isOpen && !taskId
    && connections.some(c => c.id === selectedConnectionId && ['shopee','lazada'].includes(c.platform) && c.status === 'connected')
    ? selectedConnectionId : null);
  useEffect(() => {if (useLiveMetadata) setSelectedCategory('');},[useLiveMetadata,selectedConnectionId]);
  const [brandName, setBrandName] = useState(useLiveMetadata ? '' : 'JW Audio');
  const brandMetadata = useMarketplaceListingBrands(useLiveMetadata && isOpen && !taskId
    && connections.some(c=>c.id===selectedConnectionId && c.platform==='shopee') ? selectedConnectionId : null,
    useLiveMetadata && categoryMetadata.categories.some(category=>String(category.category_id)===selectedCategory && !category.has_children) ? selectedCategory : null);
  const brandLabel=(brand:{original_brand_name:string;brand_id:number})=>`${brand.original_brand_name} (#${brand.brand_id})`;
  useEffect(()=>{if(readOnlyMetadata)setBrandName('');},[readOnlyMetadata,selectedConnectionId,selectedCategory]);
  const [packageWeightKg, setPackageWeightKg] = useState<number>(liveData?0:0.45);
  const [lengthCm, setLengthCm] = useState<number>(liveData?0:18);
  const [widthCm, setWidthCm] = useState<number>(liveData?0:14);
  const [heightCm, setHeightCm] = useState<number>(liveData?0:6);
  const [warrantyType, setWarrantyType] = useState('Manufacturer Warranty');
  const [warrantyPeriod, setWarrantyPeriod] = useState('12 Months');
  const [condition, setCondition] = useState<'NEW' | 'REFURBISHED' | 'USED'>('NEW');

  // Step 3: Pricing & Ongoing Sync
  const [markupPercent, setMarkupPercent] = useState<number>(liveData?0:5);
  const [initialStockMode, setInitialStockMode] = useState<'warehouse_stock' | 'zero_stock'>(liveData?'zero_stock':'warehouse_stock');
  const [enableOngoingSync, setEnableOngoingSync] = useState<boolean>(false); // Strictly false by default - never silently enabled
  useEffect(()=>{
    if(!liveData)return;
    const first=source?.variants[0];
    setBrandName(useLiveMetadata?'':source?.brand??'');
    setPackageWeightKg(first?.weight_grams?first.weight_grams/1000:0);
    setLengthCm(Number(first?.package_length_cm??0));
    setWidthCm(Number(first?.package_width_cm??0));
    setHeightCm(Number(first?.package_height_cm??0));
    setWarrantyType('');setWarrantyPeriod('');
  },[liveData,source]);

  // Step 4: Asynchronous Execution state
  const [simulationStatus, setAsyncStatus] = useState<PublishAsyncStatus>('idle');
  const [simulationMessage, setAsyncStepMessage] = useState<string>('');
  const [simulationExternalId, setGeneratedExternalItemId] = useState<string>('');
  const [simulationJobId, setGeneratedJobId] = useState<string>('');
  const [simulationRejection, setRejectionReason] = useState<string>('');
  const asyncStatus:PublishAsyncStatus = taskId
    ? (monitor.error || !monitor.task ? 'uncertain' : monitor.task.status === 'succeeded' ? 'success' : monitor.task.status)
    : simulationStatus;
  const generatedJobId = taskId ?? simulationJobId;
  const generatedExternalItemId = taskId ? monitor.task?.externalItemId ?? '' : simulationExternalId;
  const asyncStepMessage = taskId ? monitor.error || (monitor.task
    ? `Saved task ${taskId}: ${monitor.task.status}. No new publishing request will be sent by this view.`
    : `Checking saved task ${taskId}...`) : simulationMessage;
  const rejectionReason = taskId ? 'The saved attempt was rejected. Review its details before creating a corrected submission.' : simulationRejection;

  useEffect(() => {
    if (preselectedProductId) {
      setSelectedProductId(preselectedProductId);
    } else if (!selectedProductId && products.length > 0) {
      setSelectedProductId(products[0].id);
    }
  }, [preselectedProductId, products]);

  useEffect(() => {
    if (preselectedConnectionId) {
      setSelectedConnectionId(preselectedConnectionId);
    } else if (!selectedConnectionId && connections.length > 0) {
      setSelectedConnectionId(connections[0].id);
    }
  }, [preselectedConnectionId, connections]);

  const targetConnection = useMemo(() => {
    return connections.find(c => c.id === selectedConnectionId) || connections[0];
  }, [connections, selectedConnectionId]);

  const targetProduct = useMemo(() => {
    const selected=products.find(p => p.id === selectedProductId);
    if(!liveData)return selected;
    if(!selected || !source)return undefined;
    return {...selected,title:source.title,brand:source.brand??'',images:source.images.map(image=>image.path),
      totalInventory:0,variants:source.variants.map(variant=>({id:String(variant.id),sku:variant.sku,
        title:variant.title??variant.sku,price:variant.price_minor/100,inventoryQuantity:0}))} as Product;
  }, [products, selectedProductId,liveData,source]);
  const isLazadaLive=liveData && targetConnection?.platform==='lazada';
  const isShopeeLive=liveData && targetConnection?.platform==='shopee';
  const shopeeRequirements=useShopeePublishingRequirements(isShopeeLive&&isOpen&&!taskId?selectedConnectionId:null,selectedCategory,source?.revision??'');
  const lazadaAttributes=useLazadaPublishingAttributes(isLazadaLive?selectedConnectionId:null,selectedCategory,source);

  // Operational status check: Shopee and Lazada are live; others are in development
  const isPlatformOperational = (platform: MarketplacePlatform) => {
    return platform === 'shopee' || platform === 'lazada';
  };

  // Check if product is already listed on this connection
  const alreadyListed = useMemo(() => {
    if (!targetProduct || !targetConnection) return false;
    return marketplaceStoreProducts.some(sp => {
      if (sp.connectionId !== targetConnection.id) return false;
      return sp.variants.some(v => v.mappedJwProductId === targetProduct.id);
    });
  }, [targetProduct, targetConnection, marketplaceStoreProducts]);

  const filteredProducts = useMemo(() => {
    if (!searchQuery.trim()) return products;
    const q = searchQuery.toLowerCase();
    return products.filter(
      p =>
        p.title.toLowerCase().includes(q) ||
        p.brand?.toLowerCase().includes(q) ||
        p.variants.some(v => v.sku.toLowerCase().includes(q))
    );
  }, [products, searchQuery]);

  // Mandatory attributes validation
  const validationErrors = useMemo(() => {
    const errors: string[] = [];
    if(liveData){
      if(!source)errors.push(sourceState?.id===selectedProductId&&sourceState.error?sourceState.error:'Loading saved product details…');
      if(!submitReviewed)errors.push('Publishing submission is not connected yet.');
      if(!isLazadaLive&&!isShopeeLive)errors.push('Publishing for this platform is not connected yet.');
      if(isShopeeLive){
        if(shopeeRequirements.loading)errors.push('Loading category and shipping requirements…');
        if(shopeeRequirements.error)errors.push(shopeeRequirements.error);
        errors.push(...shopeeRequirements.errors);
        if(brandMetadata.loading)errors.push('Loading platform brands…');
        if(brandMetadata.error)errors.push(brandMetadata.error);
        if((brandMetadata.mandatory||brandName.trim())&&!brandMetadata.brands.some(brand=>brandLabel(brand)===brandName))errors.push('Choose a verified platform brand.');
        if(!['NEW','USED'].includes(condition))errors.push('Choose New or Used for this Shopee listing.');
      }
      if(categoryMetadata.loading)errors.push('Loading store categories…');
      if(categoryMetadata.error)errors.push(categoryMetadata.error);
      if(isLazadaLive){
        if(lazadaAttributes.loading)errors.push('Loading category requirements…');
        if(lazadaAttributes.error)errors.push(lazadaAttributes.error);
        for(const field of lazadaAttributes.fields){
          if((field.required || field.value.trim())&&!field.complete)errors.push(`Complete ${field.label}${field.sku?` for ${field.sku}`:''}.`);
        }
      }
    }
    if (readOnlyMetadata) {
      errors.push('Publishing is not enabled in this metadata review.');
      if (categoryMetadata.loading) errors.push('Loading store categories...');
      if (categoryMetadata.error) errors.push(categoryMetadata.error);
      if (brandMetadata.error) errors.push(brandMetadata.error);
      if (!isLazadaLive && brandName && !brandMetadata.brands.some(brand=>brandLabel(brand)===brandName)) errors.push('Choose a verified platform brand from the available choices.');
    }
    if (!isLazadaLive && !isShopeeLive && !brandName.trim()) errors.push('Brand name is required by marketplace policies.');
    if (!isLazadaLive && (!packageWeightKg || packageWeightKg <= 0)) errors.push('Package weight (kg) is mandatory for courier fee calculation.');
    if (!isLazadaLive && (!lengthCm || !widthCm || !heightCm)) errors.push('Package dimensions (L x W x H cm) are mandatory for volumetric shipping.');
    if (!selectedCategory) errors.push('Marketplace category is required.');
    return errors;
  }, [brandName, packageWeightKg, lengthCm, widthCm, heightCm, selectedCategory,readOnlyMetadata,categoryMetadata.loading,categoryMetadata.error,brandMetadata.error,brandMetadata.brands,brandMetadata.loading,brandMetadata.mandatory,condition,liveData,source,sourceState,selectedProductId,submitReviewed,isShopeeLive,shopeeRequirements.loading,shopeeRequirements.error,shopeeRequirements.errors,isLazadaLive,lazadaAttributes.loading,lazadaAttributes.error,lazadaAttributes.fields]);

  const isValidForPublish = validationErrors.length === 0 && !!targetProduct && !!targetConnection;

  // Asynchronous publish lifecycle simulation
  const handleStartPublish = async (simulatedOutcome: 'success' | 'reject' | 'uncertain' = 'success') => {
    if (taskId) {monitor.checkStatus();return;}
    if (readOnlyMetadata) return;
    if (liveData && !submitReviewed) return;
    if (!isValidForPublish || !targetProduct || !targetConnection) return;

    if (submitReviewed) {
      if (submissionStarted.current) return;
      submissionStarted.current = true;
      try {
        const id = await submitReviewed(targetProduct.id, targetConnection.id, {
          sourceRevision: source?.revision,
          ...(isLazadaLive?{lazadaAttributes:lazadaAttributes.values.normal,lazadaSkuAttributes:lazadaAttributes.values.skus}:{}),
          ...(isShopeeLive?{shopeeAttributes:shopeeRequirements.attributes,shopeeLogistics:shopeeRequirements.logistics,shopeeBrandId:brandMetadata.brands.find(brand=>brandLabel(brand)===brandName)?.brand_id??null}:{}),
          priceMultiplier: 1 + markupPercent / 100, categoryName: selectedCategory, brand: brandName,
          packageWeightKg, packageLengthCm: lengthCm, packageWidthCm: widthCm, packageHeightCm: heightCm,
          warrantyType, warrantyPeriod, condition, initialStockMode, enableOngoingSync
        });
        if (!/^[1-9][0-9]*$/.test(id)) throw new Error('Missing saved task identity');
        setSubmittedTaskId(id);
        void executeSaved(id);
      } catch (error) {
        if(error&&typeof error==='object'&&'status' in error&&typeof error.status==='number'&&error.status>=400&&error.status<500&&error.status!==404)submissionStarted.current=false;
        addToast({type:'error',title:'Check publishing request',message:error instanceof Error?error.message:'The saved task could not be confirmed. Recover the original request before submitting again.'});
      }
      return;
    }

    setCurrentStep('publishing');
    setAsyncStatus('queued');
    setAsyncStepMessage(`Validating category schema and queuing request for ${targetConnection.shopName}...`);
    const newJobId = `JOB-PUB-${Math.floor(100000 + Math.random() * 900000)}`;
    setGeneratedJobId(newJobId);

    // Transition to PROCESSING
    await new Promise(r => setTimeout(r, 900));
    setAsyncStatus('processing');
    setAsyncStepMessage(`Communicating with ${targetConnection.platform.toUpperCase()} Open API Gateway, uploading media assets, and reserving SKU barcodes...`);

    await new Promise(r => setTimeout(r, 1200));

    if (simulatedOutcome === 'reject') {
      setAsyncStatus('rejected');
      setRejectionReason(`Rejected by ${targetConnection.platform.toUpperCase()} Catalog Moderation: Category requires verified Brand Authorization Certificate for "${brandName}".`);
      return;
    }

    if (simulatedOutcome === 'uncertain') {
      setAsyncStatus('uncertain');
      setAsyncStepMessage(`Marketplace API gateway timed out awaiting fulfillment acknowledgement. Job ID ${newJobId} is queued for automated background retry.`);
      return;
    }

    // Success outcome
    const platformItemId = targetConnection.platform === 'shopee'
      ? `2948${Math.floor(1000000 + Math.random() * 9000000)}`
      : `9482${Math.floor(1000000 + Math.random() * 9000000)}`;
    
    setGeneratedExternalItemId(platformItemId);
    setAsyncStatus('success');
    setAsyncStepMessage(`Listing successfully published and confirmed by ${targetConnection.platform.toUpperCase()}.`);

    // Commit to AdminContext
    const publishOptions: PublishListingOptions = {
      priceMultiplier: 1 + markupPercent / 100,
      categoryName: selectedCategory,
      brand: brandName,
      packageWeightKg,
      packageLengthCm: lengthCm,
      packageWidthCm: widthCm,
      packageHeightCm: heightCm,
      warrantyType,
      warrantyPeriod,
      condition,
      initialStockMode,
      enableOngoingSync
    };

    publishMasterProductToStore(targetProduct.id, targetConnection.id, publishOptions);
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/70 backdrop-blur-xs animate-in fade-in duration-150">
      <div 
        id="publish-product-modal"
        className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl w-full max-w-4xl max-h-[92vh] flex flex-col shadow-2xl overflow-hidden text-neutral-900 dark:text-neutral-100"
      >
        {/* Header */}
        <div className="px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between bg-neutral-50/50 dark:bg-neutral-900/50">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
              <UploadCloud className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-base font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                Push JW Master Product to Marketplace
                <span className="text-xs px-2 py-0.5 rounded-md bg-indigo-100 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-300 font-semibold">
                  Listing Workflow
                </span>
              </h2>
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                Validate attributes, review destination, and distinguish initial listing from ongoing inventory sync.
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-2 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Wizard Progress Tabs */}
        {asyncStatus === 'idle' && (
          <div className="px-6 py-3 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-900 flex items-center justify-between text-xs overflow-x-auto">
            <div className="flex items-center gap-6 min-w-max">
              <button
                type="button"
                onClick={() => setCurrentStep('destination')}
                className={`flex items-center gap-2 font-semibold transition-colors cursor-pointer ${
                  currentStep === 'destination'
                    ? 'text-indigo-600 dark:text-indigo-400'
                    : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'
                }`}
              >
                <span className={`w-5 h-5 rounded-full flex items-center justify-center text-[10px] ${
                  currentStep === 'destination' ? 'bg-indigo-600 text-white' : 'bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300'
                }`}>1</span>
                <span>Destination & Product</span>
              </button>

              <ChevronRight className="w-3.5 h-3.5 text-neutral-300 dark:text-neutral-600" />

              <button
                type="button"
                onClick={() => setCurrentStep('attributes')}
                className={`flex items-center gap-2 font-semibold transition-colors cursor-pointer ${
                  currentStep === 'attributes'
                    ? 'text-indigo-600 dark:text-indigo-400'
                    : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'
                }`}
              >
                <span className={`w-5 h-5 rounded-full flex items-center justify-center text-[10px] ${
                  currentStep === 'attributes' ? 'bg-indigo-600 text-white' : 'bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300'
                }`}>2</span>
                <span>Category & Attributes</span>
              </button>

              <ChevronRight className="w-3.5 h-3.5 text-neutral-300 dark:text-neutral-600" />

              <button
                type="button"
                onClick={() => setCurrentStep('pricing_sync')}
                className={`flex items-center gap-2 font-semibold transition-colors cursor-pointer ${
                  currentStep === 'pricing_sync'
                    ? 'text-indigo-600 dark:text-indigo-400'
                    : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'
                }`}
              >
                <span className={`w-5 h-5 rounded-full flex items-center justify-center text-[10px] ${
                  currentStep === 'pricing_sync' ? 'bg-indigo-600 text-white' : 'bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300'
                }`}>3</span>
                <span>Pricing & Sync Rules</span>
              </button>
            </div>

            <div className="hidden sm:flex items-center gap-1 text-[11px] text-neutral-400">
              <ShieldCheck className="w-3.5 h-3.5 text-emerald-500" />
              <span>Server-side OAuth Auth</span>
            </div>
          </div>
        )}

        {/* Content Body */}
        <div className="p-6 overflow-y-auto flex-1 space-y-6">
          {/* STEP 4: ASYNCHRONOUS PUBLISHING STATES */}
          {currentStep === 'publishing' ? (
            <div className="py-6 px-4 space-y-6 max-w-2xl mx-auto text-center">
              {/* QUEUED & PROCESSING STATE */}
              {(asyncStatus === 'queued' || asyncStatus === 'processing') && (
                <div className="space-y-6">
                  <div className="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 mx-auto flex items-center justify-center animate-pulse">
                    <RefreshCw className="w-8 h-8 animate-spin" />
                  </div>

                  <div className="space-y-2">
                    <h3 className="text-lg font-bold text-neutral-900 dark:text-white">
                      {asyncStatus === 'queued' ? 'Queued for Marketplace Dispatch' : 'Communicating with Open API Gateway'}
                    </h3>
                    <p className="text-sm text-neutral-600 dark:text-neutral-300">
                      {asyncStepMessage}
                    </p>
                    <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-neutral-100 dark:bg-neutral-800 text-xs font-mono text-neutral-600 dark:text-neutral-400">
                      <Clock className="w-3.5 h-3.5" />
                      Job Reference: {generatedJobId}
                    </div>
                    {taskId&&asyncStatus==='queued'&&<button type="button" disabled={dispatching} onClick={()=>executeSaved(taskId)} className="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold disabled:opacity-50">{dispatching?'Starting saved task…':'Start saved publishing task'}</button>}
                  </div>

                  {/* Asynchronous Step Tracker */}
                  <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700/60 text-left space-y-3">
                    <div className="flex items-center gap-3 text-xs">
                      <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0" />
                      <span className="text-neutral-700 dark:text-neutral-200 font-medium">{taskId ? '1. Reading the persisted publishing task' : '1. Local catalog & mandatory attributes payload verified'}</span>
                    </div>
                    <div className="flex items-center gap-3 text-xs">
                      <RefreshCw className="w-4 h-4 text-indigo-500 animate-spin shrink-0" />
                      <span className="text-neutral-900 dark:text-white font-semibold">{taskId ? `2. Destination store #${monitor.task?.storeId ?? '—'}` : `2. Dispatching to ${targetConnection.shopName} (${targetConnection.platform.toUpperCase()})`}</span>
                    </div>
                    <div className="flex items-center gap-3 text-xs text-neutral-400">
                      <div className="w-4 h-4 rounded-full border border-neutral-300 dark:border-neutral-600 flex items-center justify-center text-[10px]">3</div>
                      <span>3. Awaiting external platform item ID and establishing 1:1 auto-link</span>
                    </div>
                  </div>
                </div>
              )}

              {/* SUCCESS STATE */}
              {asyncStatus === 'success' && (
                <div className="space-y-6 animate-in fade-in">
                  <div className="w-16 h-16 rounded-2xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 mx-auto flex items-center justify-center">
                    <CheckCircle2 className="w-9 h-9" />
                  </div>

                  <div className="space-y-2">
                    <h3 className="text-lg font-bold text-neutral-900 dark:text-white">
                      {taskId ? 'Listing Task Completed' : 'Listing Successfully Published & Live!'}
                    </h3>
                    <p className="text-sm text-neutral-600 dark:text-neutral-300">
                      {taskId ? `Task ${taskId} is confirmed complete. Check the channel listing for its current visibility.` : <>"{targetProduct?.title}" is now active on <strong>{targetConnection.shopName}</strong> ({targetConnection.platform.toUpperCase()}).</>}
                    </p>
                  </div>

                  {/* Real External IDs Card */}
                  <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 text-left space-y-3">
                    <div className="flex items-center justify-between pb-2 border-b border-neutral-200 dark:border-neutral-700/60 text-xs">
                      <span className="text-neutral-500 dark:text-neutral-400 font-medium">Channel Platform Item ID</span>
                      <span className="font-mono font-bold text-neutral-900 dark:text-white bg-neutral-200 dark:bg-neutral-700 px-2 py-0.5 rounded">
                        {generatedExternalItemId}
                      </span>
                    </div>

                    <div className="flex items-center justify-between text-xs">
                      <span className="text-neutral-500 dark:text-neutral-400 font-medium">Destination Store</span>
                      <span className="font-semibold text-neutral-800 dark:text-neutral-200 flex items-center gap-1.5">
                        {!taskId && <MarketplaceLogo platform={targetConnection.platform} size={16} />}
                        {taskId ? `Store #${monitor.task?.storeId}` : `${targetConnection.shopName} (Malaysia)`}
                      </span>
                    </div>

                    <div className="flex items-center justify-between text-xs">
                      <span className="text-neutral-500 dark:text-neutral-400 font-medium">Variants Linked</span>
                      <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                        {taskId ? 'Check Mapping Workspace' : `${targetProduct?.variants.length} SKUs auto-linked (1:1)`}
                      </span>
                    </div>

                    <div className="flex items-center justify-between text-xs">
                      <span className="text-neutral-500 dark:text-neutral-400 font-medium">Ongoing Inventory Sync</span>
                      <span className={`font-semibold px-2 py-0.5 rounded ${
                        enableOngoingSync 
                          ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                          : 'bg-neutral-200 text-neutral-700 dark:bg-neutral-700 dark:text-neutral-300'
                      }`}>
                        {taskId ? 'Check Store Sync Settings' : enableOngoingSync ? 'Active (Real-time Broadcast)' : 'Listing Only (Sync Disabled)'}
                      </span>
                    </div>
                  </div>

                  <div className="flex flex-wrap items-center justify-center gap-3 pt-2">
                    <button
                      type="button"
                      onClick={() => {
                        onClose();
                        setActiveSection('marketplace_mapping');
                      }}
                      className="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-colors cursor-pointer"
                    >
                      View in Mapping Workspace
                    </button>

                    <button
                      type="button"
                      onClick={onClose}
                      className="px-4 py-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-bold transition-colors cursor-pointer"
                    >
                      Done & Close
                    </button>
                  </div>
                </div>
              )}

              {/* REJECTED STATE (SIMULATED HANDLING) */}
              {asyncStatus === 'rejected' && (
                <div className="space-y-6 animate-in fade-in">
                  <div className="w-16 h-16 rounded-2xl bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 mx-auto flex items-center justify-center">
                    <AlertCircle className="w-9 h-9" />
                  </div>

                  <div className="space-y-2">
                    <h3 className="text-lg font-bold text-neutral-900 dark:text-white">
                      {taskId ? 'Listing Attempt Rejected' : 'Listing Rejected by Channel Moderation'}
                    </h3>
                    <p className="text-sm text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 p-3 rounded-xl border border-rose-200 dark:border-rose-900 text-left">
                      {rejectionReason}
                    </p>
                  </div>

                  <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 text-left text-xs space-y-2">
                    <div className="font-bold text-neutral-900 dark:text-white">Remediation Steps:</div>
                    <ul className="list-disc pl-4 space-y-1 text-neutral-600 dark:text-neutral-300">
                      <li>Select "No Brand / OEM" if you do not hold a registered trademark certificate.</li>
                      <li>Review title keywords to avoid prohibited promotional superlatives (e.g. "Best", "Top #1").</li>
                      <li>Adjust your category taxonomy and re-submit.</li>
                    </ul>
                  </div>

                  <div className="flex items-center justify-center gap-3">
                    <button
                      type="button"
                      onClick={() => {
                        if (taskId) {monitor.checkStatus();return;}
                        setBrandName('No Brand / OEM');
                        setCurrentStep('attributes');
                        setAsyncStatus('idle');
                      }}
                      className="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-colors cursor-pointer"
                    >
                      {taskId ? 'Check Saved Status' : 'Edit Attributes & Retry'}
                    </button>

                    <button
                      type="button"
                      onClick={onClose}
                      className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 text-xs font-semibold cursor-pointer"
                    >
                      Cancel
                    </button>
                  </div>
                </div>
              )}

              {/* UNCERTAIN / TIMEOUT STATE */}
              {asyncStatus === 'uncertain' && (
                <div className="space-y-6 animate-in fade-in">
                  <div className="w-16 h-16 rounded-2xl bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 mx-auto flex items-center justify-center">
                    <Clock className="w-9 h-9" />
                  </div>

                  <div className="space-y-2">
                    <h3 className="text-lg font-bold text-neutral-900 dark:text-white">
                      {taskId ? 'Result Requires Verification' : 'Outcome Uncertain (Gateway Timeout)'}
                    </h3>
                    <p className="text-xs text-neutral-600 dark:text-neutral-400">
                      {asyncStepMessage}
                    </p>
                  </div>

                  <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800 text-left text-xs space-y-2 border border-neutral-200 dark:border-neutral-700">
                    <div className="font-semibold text-neutral-800 dark:text-neutral-200">{taskId ? 'Saved Task Reference:' : 'Asynchronous Recovery in Progress:'}</div>
                    <p className="text-neutral-500">
                      {taskId ? <>Task <strong>{generatedJobId}</strong> remains saved. Check its status without resubmitting the product.</> : <>JW Ecommerce background worker is polling the marketplace gateway using Job ID <strong>{generatedJobId}</strong>. When Shopee/Lazada completes indexing, the item will automatically map.</>}
                    </p>
                  </div>

                  <div className="flex items-center justify-center gap-3">
                    <button
                      type="button"
                      onClick={() => handleStartPublish('success')}
                      className="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-colors cursor-pointer flex items-center gap-1.5"
                    >
                      <RefreshCw className="w-3.5 h-3.5" />
                      Poll Status Now
                    </button>

                    <button
                      type="button"
                      onClick={onClose}
                      className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-600 text-xs font-semibold cursor-pointer"
                    >
                      Close & Check Sync Logs Later
                    </button>
                  </div>
                </div>
              )}
            </div>
          ) : (
            /* STEP 1, 2, 3 CONFIGURATION SCREENS */
            <>
              {/* STEP 1: DESTINATION & PRODUCT */}
              {currentStep === 'destination' && (
                <div className="space-y-6">
                  {/* Destination Selection */}
                  <div className="space-y-3">
                    <div className="flex items-center justify-between">
                      <label className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                        <Store className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                        1. Select Destination Store (Malaysia)
                      </label>
                      <span className="text-[11px] text-neutral-500 dark:text-neutral-400">
                        {liveData ? 'Connected Shopee & Lazada stores' : 'Shopee Malaysia (Live) · Lazada (Live)'}
                      </span>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                      {connections.map(conn => {
                        const isOperational = isPlatformOperational(conn.platform);
                        const isSelected = conn.id === selectedConnectionId;

                        return (
                          <div
                            key={conn.id}
                            onClick={() => {
                              if (isOperational) {
                                setSelectedConnectionId(conn.id);
                              }
                            }}
                            className={`p-3.5 rounded-xl border transition-all flex flex-col justify-between gap-2 ${
                              !isOperational
                                ? 'opacity-50 bg-neutral-100 dark:bg-neutral-800/40 border-neutral-200 dark:border-neutral-700 cursor-not-allowed'
                                : isSelected
                                ? 'bg-indigo-50/50 dark:bg-indigo-950/40 border-indigo-600 dark:border-indigo-500 text-neutral-900 dark:text-white ring-1 ring-indigo-600 dark:ring-indigo-500 cursor-pointer shadow-xs'
                                : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700 hover:bg-neutral-50/50 dark:hover:bg-neutral-800/50 cursor-pointer'
                            }`}
                          >
                            <div className="flex items-center justify-between">
                              <MarketplaceLogo platform={conn.platform} size={22} />
                              {isOperational ? (
                                <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300">
                                  {liveData ? 'Connected' : 'Live Integration'}
                                </span>
                              ) : (
                                <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-400">
                                  R2 Development
                                </span>
                              )}
                            </div>

                            <div>
                              <div className="text-xs font-bold text-neutral-900 dark:text-white truncate">
                                {conn.shopName}
                              </div>
                              <div className="text-[11px] text-neutral-500 dark:text-neutral-400">
                                Region: Malaysia (MYR)
                              </div>
                            </div>

                            {isSelected && isOperational && (
                              <div className="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 flex items-center gap-1">
                                <Check className="w-3.5 h-3.5" />
                                Selected Destination
                              </div>
                            )}
                          </div>
                        );
                      })}
                    </div>
                  </div>

                  {/* Product Selection */}
                  <div className="space-y-3">
                    <div className="flex items-center justify-between">
                      <label className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                        <Package className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                        2. Select JW Master Product to Push
                      </label>
                      <span className="text-[11px] text-neutral-500 dark:text-neutral-400">
                        {products.length} master products available
                      </span>
                    </div>

                    <div className="relative">
                      <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
                      <input
                        type="text"
                        value={searchQuery}
                        onChange={e => setSearchQuery(e.target.value)}
                        placeholder="Search product title, brand, or SKU..."
                        className="w-full pl-9 pr-4 py-2 bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-700 rounded-xl text-xs text-neutral-900 dark:text-white placeholder-neutral-400 dark:placeholder-neutral-500 focus:outline-hidden focus:ring-1 focus:ring-indigo-500"
                      />
                    </div>

                    <div className="max-h-56 overflow-y-auto border border-neutral-200 dark:border-neutral-800 rounded-xl divide-y divide-neutral-100 dark:divide-neutral-800/80 bg-white dark:bg-neutral-950">
                      {filteredProducts.map(p => {
                        const isSelected = p.id === selectedProductId;
                        const isAlreadyOnChannel = targetConnection && marketplaceStoreProducts.some(sp => {
                          return sp.connectionId === targetConnection.id && sp.variants.some(v => v.mappedJwProductId === p.id);
                        });

                        return (
                          <div
                            key={p.id}
                            onClick={() => setSelectedProductId(p.id)}
                            className={`p-3 flex items-center justify-between cursor-pointer transition-colors ${
                              isSelected
                                ? 'bg-indigo-50/50 dark:bg-indigo-950/20'
                                : 'hover:bg-neutral-50 dark:hover:bg-neutral-900'
                            }`}
                          >
                            <div className="flex items-center gap-3 min-w-0">
                              <img
                                src={p.images[0] || 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=100'}
                                alt={p.title}
                                className="w-10 h-10 rounded-lg object-cover border border-neutral-200 dark:border-neutral-700 shrink-0"
                              />
                              <div className="min-w-0">
                                <div className="flex items-center gap-2">
                                  <p className="text-xs font-bold text-neutral-900 dark:text-white truncate">{p.title}</p>
                                  {isAlreadyOnChannel && (
                                    <span className="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800 shrink-0">
                                      Already on {targetConnection.platform.toUpperCase()}
                                    </span>
                                  )}
                                </div>
                                <p className="text-[11px] text-neutral-500 dark:text-neutral-400">
                                  {p.variants.length} variant(s) · Base MYR {p.basePrice.toFixed(2)} · {liveData ? 'JW Legacy Stock' : 'Physical Stock'}: {p.totalInventory} units
                                </p>
                              </div>
                            </div>

                            <div className="shrink-0 flex items-center pl-2">
                              <div className={`w-5 h-5 rounded-full border flex items-center justify-center ${
                                isSelected ? 'bg-indigo-600 border-indigo-600 text-white' : 'border-neutral-300 dark:border-neutral-600'
                              }`}>
                                {isSelected && <Check className="w-3 h-3" />}
                              </div>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                </div>
              )}

              {/* STEP 2: CATEGORY & REQUIRED ATTRIBUTES */}
              {currentStep === 'attributes' && (
                <div className="space-y-5">
                  <div className="p-3.5 rounded-xl bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800/50 flex items-start gap-3">
                    <Info className="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5" />
                    <div className="text-xs text-indigo-950 dark:text-indigo-200">
                      <strong>Backend Category & Logistics Validation:</strong> Shopee SPX and Lazada LEX require verified weight, dimensions, and brand attributes before an external listing can be registered.
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {/* Category */}
                    <div className="space-y-1.5 sm:col-span-2">
                      <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300 flex items-center gap-1">
                        <span>Marketplace Category Taxonomy</span>
                        <span className="text-rose-500">*</span>
                      </label>
                      <select
                        value={selectedCategory}
                        disabled={useLiveMetadata && (categoryMetadata.loading || !!categoryMetadata.error)}
                        onChange={e => setSelectedCategory(e.target.value)}
                        className="w-full px-3 py-2 bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs text-neutral-900 dark:text-white focus:ring-1 focus:ring-indigo-500"
                      >
                        {useLiveMetadata ? <>
                          <option value="">{categoryMetadata.loading ? 'Loading store categories...' : 'Select a marketplace category'}</option>
                          {categoryMetadata.categories.map(category => <option key={category.category_id}
                            value={String(category.category_id)} disabled={category.has_children}>
                            {category.display_category_name || category.original_category_name} ({category.category_id})
                          </option>)}
                        </> : <>
                        <option value="Consumer Electronics & Audio > Headphones">Consumer Electronics &amp; Audio &gt; Headphones &amp; Earphones</option>
                        <option value="Consumer Electronics > Audio > Portable Speakers">Consumer Electronics &gt; Audio &gt; Portable Speakers</option>
                        <option value="Mobile & Gadgets > Audio Accessories">Mobile &amp; Gadgets &gt; Audio Accessories</option>
                        <option value="Computers & Accessories > Gaming Headsets">Computers &amp; Accessories &gt; Gaming Headsets</option>
                        </>}
                      </select>
                    </div>

                    {/* Brand */}
                    <div hidden={isLazadaLive} className="space-y-1.5">
                      <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300 flex items-center gap-1">
                        <span>Brand Name</span>
                        <span className="text-rose-500">*</span>
                      </label>
                      {isShopeeLive ? <select aria-label="Shopee platform brand"
                        value={brandMetadata.brands.some(b=>brandLabel(b)===brandName)?brandName:''}
                        disabled={!selectedCategory||brandMetadata.loading}
                        onChange={e=>setBrandName(e.target.value)}
                        className="w-full px-3 py-2 bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs text-neutral-900 dark:text-white focus:ring-1 focus:ring-indigo-500">
                        <option value="">Select a platform brand</option>
                        {brandMetadata.brands.map(b=><option key={b.brand_id} value={brandLabel(b)}>{b.display_brand_name??b.original_brand_name}</option>)}
                      </select> : <input
                        type="text"
                        value={brandName}
                        list={useLiveMetadata ? 'marketplace-publish-brand-options' : undefined}
                        disabled={useLiveMetadata && (!selectedCategory || brandMetadata.loading)}
                        onChange={e => setBrandName(e.target.value)}
                        className="w-full px-3 py-2 bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs text-neutral-900 dark:text-white focus:ring-1 focus:ring-indigo-500"
                        placeholder={useLiveMetadata ? 'Select a platform brand' : 'e.g. JW Audio or No Brand'}
                      />}
                      {useLiveMetadata && <datalist id="marketplace-publish-brand-options">
                        {brandMetadata.brands.map(brand=><option key={brand.brand_id} value={brandLabel(brand)}>{brand.original_brand_name}</option>)}
                      </datalist>}
                      <div className="text-[10px] text-neutral-400">
                        {useLiveMetadata ? <>
                          {brandMetadata.loading ? 'Loading platform brands...' : 'Choose the platform brand and ID; do not substitute an unverified brand name.'}
                          {brandMetadata.hasMore && <button type="button" onClick={brandMetadata.loadMore}>Load more brands</button>}
                          {brandMetadata.error && <button type="button" onClick={brandMetadata.retry}>Retry brands</button>}
                        </> : <>Use "No Brand / OEM" if you don't possess a brand authorization letter.</>}
                      </div>
                    </div>

                    {/* Condition */}
                    <div hidden={isLazadaLive} className="space-y-1.5">
                      <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                        Item Condition
                      </label>
                      <select
                        value={condition}
                        onChange={e => setCondition(e.target.value as any)}
                        className="w-full px-3 py-2 bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs text-neutral-900 dark:text-white"
                      >
                        <option value="NEW">Brand New (Sealed Retail Box)</option>
                        {isShopeeLive?<option value="USED">Used</option>:<option value="REFURBISHED">Certified Refurbished</option>}
                      </select>
                    </div>

                    {/* Package Weight (kg) */}
                    <div hidden={isLazadaLive} className="space-y-1.5">
                      <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300 flex items-center gap-1">
                        <span>Package Weight (kg)</span>
                        <span className="text-rose-500">*</span>
                      </label>
                      <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        value={packageWeightKg}
                        onChange={e => setPackageWeightKg(parseFloat(e.target.value) || 0)}
                        className="w-full px-3 py-2 bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs text-neutral-900 dark:text-white font-mono"
                      />
                      <div className="text-[10px] text-neutral-400">
                        Used by Shopee Express / LEX for shipping tier computation.
                      </div>
                    </div>

                    {/* Dimensions (L x W x H cm) */}
                    <div hidden={isLazadaLive} className="space-y-1.5">
                      <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300 flex items-center gap-1">
                        <span>Dimensions L x W x H (cm)</span>
                        <span className="text-rose-500">*</span>
                      </label>
                      <div className="grid grid-cols-3 gap-1.5">
                        <input
                          type="number"
                          placeholder="L"
                          value={lengthCm}
                          onChange={e => setLengthCm(parseInt(e.target.value) || 0)}
                          className="px-2 py-2 text-center bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs font-mono"
                        />
                        <input
                          type="number"
                          placeholder="W"
                          value={widthCm}
                          onChange={e => setWidthCm(parseInt(e.target.value) || 0)}
                          className="px-2 py-2 text-center bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs font-mono"
                        />
                        <input
                          type="number"
                          placeholder="H"
                          value={heightCm}
                          onChange={e => setHeightCm(parseInt(e.target.value) || 0)}
                          className="px-2 py-2 text-center bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs font-mono"
                        />
                      </div>
                      <div className="text-[10px] text-neutral-400">
                        Volumetric weight calculation compliance.
                      </div>
                    </div>

                    {/* Warranty */}
                    <div hidden={isLazadaLive} className="space-y-1.5">
                      <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                        Warranty Type
                      </label>
                      <input
                        type="text"
                        value={warrantyType}
                        onChange={e => setWarrantyType(e.target.value)}
                        className="w-full px-3 py-2 bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs"
                      />
                    </div>

                    <div hidden={isLazadaLive} className="space-y-1.5">
                      <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                        Warranty Period
                      </label>
                      <input
                        type="text"
                        value={warrantyPeriod}
                        onChange={e => setWarrantyPeriod(e.target.value)}
                        className="w-full px-3 py-2 bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs"
                      />
                    </div>
                  </div>

                  {isLazadaLive&&<div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <LazadaPublishingAttributes fields={lazadaAttributes.fields} onChange={lazadaAttributes.update}/>
                    {lazadaAttributes.error&&<button type="button" className="text-xs font-bold text-neutral-700 dark:text-neutral-300" onClick={lazadaAttributes.retry}>Retry category requirements</button>}
                    {lazadaAttributes.moreBrands&&<button type="button" className="text-xs font-bold text-neutral-700 dark:text-neutral-300" disabled={lazadaAttributes.moreLoading} onClick={lazadaAttributes.loadMore}>{lazadaAttributes.moreLoading?'Loading brands…':'Load more brands'}</button>}
                    {lazadaAttributes.moreError&&<p className="text-xs text-rose-500">{lazadaAttributes.moreError}</p>}
                  </div>}
                  {/* Validation Error Banner */}
                  {isShopeeLive&&<ShopeePublishingRequirements review={shopeeRequirements}/>}
                  {validationErrors.length > 0 ? (
                    <div className="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-xs text-rose-700 dark:text-rose-300 space-y-1">
                      <div className="font-bold flex items-center gap-1.5">
                        <AlertCircle className="w-3.5 h-3.5" />
                        Incomplete Mandatory Attributes:
                      </div>
                      <ul className="list-disc pl-5">
                        {validationErrors.map((err, i) => (
                          <li key={i}>{err}</li>
                        ))}
                      </ul>
                    </div>
                  ) : (
                    <div className="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 text-xs text-emerald-800 dark:text-emerald-300 flex items-center gap-2">
                      <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
                      <span>All required platform attributes validated and compliant with {targetConnection.shopName}.</span>
                    </div>
                  )}
                </div>
              )}

              {/* STEP 3: PRICING & INVENTORY SYNC RULES */}
              {currentStep === 'pricing_sync' && targetProduct && (
                <div className="space-y-5">
                  {/* Pricing markup */}
                  <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60 space-y-3">
                    <div className="flex items-center justify-between">
                      <label className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                        <TrendingUp className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                        {liveData ? 'JW Product Prices' : 'Marketplace Pricing Markup'}
                      </label>
                      <span className="text-xs font-bold text-emerald-600 dark:text-emerald-400 font-mono">
                        +{markupPercent}%
                      </span>
                    </div>
                    <p className="text-[11px] text-neutral-500 dark:text-neutral-400">
                      {liveData ? 'Uses the saved JW price for each enabled variant.' : 'Compensate for platform transaction and commission fees. Master catalog prices remain unchanged.'}
                    </p>
                    <div className="flex items-center gap-2">
                      {(liveData ? [0] : [0, 5, 8, 12, 15]).map(val => (
                        <button
                          key={val}
                          type="button"
                          disabled={liveData}
                          onClick={() => {if(!liveData)setMarkupPercent(val);}}
                          className={`px-3 py-1.5 text-xs rounded-lg font-semibold transition-colors cursor-pointer ${
                            markupPercent === val
                              ? 'bg-emerald-600 text-white shadow-xs'
                              : 'bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border border-neutral-300 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-700'
                          }`}
                        >
                          +{val}%
                        </button>
                      ))}
                    </div>
                  </div>

                  {/* Stock Strategy & Ongoing Sync: Explicit Distinction */}
                  <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60 space-y-4">
                    <div>
                      <h4 className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                        <Boxes className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                        Distinction: Initial Listing vs Ongoing Sync
                      </h4>
                      <p className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5">
                        Never silently copied. You control whether to allocate initial stock and whether to activate live sync.
                      </p>
                    </div>

                    {/* Initial Listing Stock Mode */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                      <label 
                        onClick={() => {if(!liveData)setInitialStockMode('warehouse_stock');}}
                        className={`p-3 rounded-xl border transition-all cursor-pointer flex items-start gap-2.5 ${
                          initialStockMode === 'warehouse_stock'
                            ? 'border-indigo-600 bg-white dark:bg-neutral-800 shadow-xs'
                            : 'border-neutral-200 dark:border-neutral-700 bg-white/50 dark:bg-neutral-900/40 opacity-70'
                        }`}
                      >
                        <input
                          type="radio"
                          name="initialStockMode"
                          disabled={liveData}
                          checked={initialStockMode === 'warehouse_stock'}
                          onChange={() => setInitialStockMode('warehouse_stock')}
                          className="mt-0.5 text-indigo-600"
                        />
                        <div>
                          <div className="text-xs font-bold text-neutral-900 dark:text-white">
                            Allocate Current Warehouse Stock
                          </div>
                          <div className="text-[11px] text-neutral-500 dark:text-neutral-400">
                            {liveData ? 'Unavailable in Basic WMS. Existing inventory is not allocated by publishing.' : `Pushes current physical stock (${targetProduct.totalInventory} units total across variants).`}
                          </div>
                        </div>
                      </label>

                      <label 
                        onClick={() => setInitialStockMode('zero_stock')}
                        className={`p-3 rounded-xl border transition-all cursor-pointer flex items-start gap-2.5 ${
                          initialStockMode === 'zero_stock'
                            ? 'border-indigo-600 bg-white dark:bg-neutral-800 shadow-xs'
                            : 'border-neutral-200 dark:border-neutral-700 bg-white/50 dark:bg-neutral-900/40 opacity-70'
                        }`}
                      >
                        <input
                          type="radio"
                          name="initialStockMode"
                          checked={initialStockMode === 'zero_stock'}
                          onChange={() => setInitialStockMode('zero_stock')}
                          className="mt-0.5 text-indigo-600"
                        />
                        <div>
                          <div className="text-xs font-bold text-neutral-900 dark:text-white">
                            Zero Initial Stock (Out of Stock / Preorder)
                          </div>
                          <div className="text-[11px] text-neutral-500 dark:text-neutral-400">
                            Lists external item with 0 stock to prevent orders until inventory is received.
                          </div>
                        </div>
                      </label>
                    </div>

                    {/* Explicit Ongoing Sync Toggle */}
                    <div className="pt-3 border-t border-neutral-200 dark:border-neutral-700">
                      <label className="flex items-start gap-3 cursor-pointer">
                        <input
                          type="checkbox"
                          disabled={liveData}
                          checked={enableOngoingSync}
                          onChange={e => setEnableOngoingSync(e.target.checked)}
                          className="mt-1 rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <div className="space-y-0.5">
                          <div className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                            Enable Ongoing Real-Time Inventory Sync
                            <span className="text-[10px] font-semibold px-2 py-0.2 rounded bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300">
                              Optional
                            </span>
                          </div>
                          <p className="text-[11px] text-neutral-500 dark:text-neutral-400">
                            {liveData ? 'Ongoing stock synchronization is disabled in Basic WMS. Publishing does not take over inventory.' : `When enabled, any physical warehouse stock changes in JW will immediately broadcast updates to ${targetConnection.shopName}. Leave unchecked if you manage marketplace stock manually.`}
                          </p>
                        </div>
                      </label>
                    </div>
                  </div>

                  {/* Variants & Channel Price Preview Table */}
                  <div className="space-y-2">
                    <span className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
                      Variants & Channel Listing Preview ({targetProduct.variants.length} SKUs)
                    </span>
                    <div className="border border-neutral-200 dark:border-neutral-800 rounded-xl overflow-hidden bg-white dark:bg-neutral-900">
                      <table className="w-full text-left text-xs">
                        <thead className="bg-neutral-50 dark:bg-neutral-800/80 text-neutral-600 dark:text-neutral-300 font-semibold border-b border-neutral-200 dark:border-neutral-700">
                          <tr>
                            <th className="py-2.5 px-3">Variant</th>
                            <th className="py-2.5 px-3">Master SKU</th>
                            <th className="py-2.5 px-3">Master Price</th>
                            <th className="py-2.5 px-3">Channel Price (+{markupPercent}%)</th>
                            <th className="py-2.5 px-3">Listing Stock</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                          {targetProduct.variants.map(v => {
                            const channelPrice = +(v.price * (1 + markupPercent / 100)).toFixed(2);
                            const displayStock = initialStockMode === 'zero_stock' ? 0 : v.inventoryQuantity;

                            return (
                              <tr key={v.id} className="hover:bg-neutral-50/50 dark:hover:bg-neutral-800/30">
                                <td className="py-2.5 px-3 font-semibold text-neutral-900 dark:text-white">{v.title}</td>
                                <td className="py-2.5 px-3 font-mono text-indigo-600 dark:text-indigo-400">{v.sku}</td>
                                <td className="py-2.5 px-3 text-neutral-500 dark:text-neutral-400">MYR {v.price.toFixed(2)}</td>
                                <td className="py-2.5 px-3 font-bold text-emerald-600 dark:text-emerald-400">MYR {channelPrice.toFixed(2)}</td>
                                <td className="py-2.5 px-3">
                                  <span className="px-2 py-0.5 rounded text-xs font-bold bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200">
                                    {displayStock} units
                                  </span>
                                </td>
                              </tr>
                            );
                          })}
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              )}
            </>
          )}
        </div>

        {/* Footer Actions */}
        {asyncStatus === 'idle' && (
          <div className="px-6 py-4 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-900/50 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div className="text-xs text-neutral-500 dark:text-neutral-400 flex items-center gap-1.5">
              <ShieldCheck className="w-4 h-4 text-emerald-600 shrink-0" />
              <span>
                {currentStep === 'destination' && 'Selected target: ' + targetConnection?.shopName}
                {currentStep === 'attributes' && (isValidForPublish ? 'Attributes verified' : 'Complete required fields')}
                {currentStep === 'pricing_sync' && (enableOngoingSync ? 'Ongoing sync will be enabled' : 'Publishing listing only (no auto-sync)')}
              </span>
            </div>

            <div className="flex items-center gap-2.5 w-full sm:w-auto justify-end">
              {currentStep !== 'destination' && (
                <button
                  type="button"
                  onClick={() => {
                    if (currentStep === 'pricing_sync') setCurrentStep('attributes');
                    else if (currentStep === 'attributes') setCurrentStep('destination');
                  }}
                  className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 text-xs font-semibold hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
                >
                  Back
                </button>
              )}

              {currentStep === 'destination' && (
                <button
                  type="button"
                  disabled={!targetProduct || !targetConnection}
                  onClick={() => setCurrentStep('attributes')}
                  className="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                >
                  <span>Next: Attributes</span>
                  <ArrowRight className="w-3.5 h-3.5" />
                </button>
              )}

              {currentStep === 'attributes' && (
                <button
                  type="button"
                  disabled={validationErrors.length > 0}
                  onClick={() => setCurrentStep('pricing_sync')}
                  className="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                >
                  <span>Next: Pricing & Sync</span>
                  <ArrowRight className="w-3.5 h-3.5" />
                </button>
              )}

              {currentStep === 'pricing_sync' && (
                <div className="flex items-center gap-2">
                  {/* Test Simulation dropdown for rejected/uncertain testing */}
                  <div className="relative group">
                    <button
                      type="button"
                      onClick={() => handleStartPublish('success')}
                      className="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-xs flex items-center gap-2 cursor-pointer"
                    >
                      <Sparkles className="w-4 h-4" />
                      Publish to {targetConnection.platform.toUpperCase()}
                    </button>
                  </div>
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

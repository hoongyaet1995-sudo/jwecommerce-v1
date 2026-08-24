import React, { useEffect, useMemo, useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import type { AdminProductReview, AdminProductReviewAuditEvent, ReviewModerationAction } from '../../types';
import {
  AlertCircle,
  Check,
  CheckCircle2,
  ChevronRight,
  Clock,
  ExternalLink,
  Eye,
  Image as ImageIcon,
  Maximize2,
  MessageSquare,
  Package,
  Search,
  ShieldCheck,
  ShoppingBag,
  Star,
  X,
  XCircle,
} from 'lucide-react';

const REJECTION_PRESETS = [
  'Inappropriate language, profanity, or harassment',
  'Advertising, spam, or competitor website links',
  'Review is not related to the product purchased',
  'Contains personal contact info / PII (phone number, address)',
  'Shipping/courier delivery dispute (routed to Customer Support)',
  'Suspected fraudulent or duplicate submission',
];

const reviewPhotoUrls = (review: AdminProductReview): string[] => review.photos.map((photo) => photo.url);

const auditEventSummary = (event: AdminProductReviewAuditEvent): string => {
  if (event.eventType === 'moderated' && event.fromStatus && event.toStatus) {
    return `${event.fromStatus} -> ${event.toStatus}`;
  }

  return event.eventType.replace(/_/g, ' ');
};

export const ProductReviewsAdmin: React.FC = () => {
  const {
    reviews = [],
    products = [],
    reviewFilterProductId,
    setReviewFilterProductId,
    moderateReview,
    bulkModerateReviews,
    fetchReviewAudit,
    canModerateReviews,
    canBulkModerateReviews,
    canViewReviewAudit,
    setCurrentSection,
  } = useAdmin();

  const [selectedReviewId, setSelectedReviewId] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<'pending' | 'published' | 'rejected' | 'all'>('pending');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedProductFilter, setSelectedProductFilter] = useState<string>(reviewFilterProductId || 'all');
  const [ratingFilter, setRatingFilter] = useState<string>('all');
  const [hasPhotosOnly, setHasPhotosOnly] = useState(false);
  const [isDetailOpen, setIsDetailOpen] = useState(false);
  const [activeReviewForDrawer, setActiveReviewForDrawer] = useState<AdminProductReview | null>(null);
  const [isRejectModalOpen, setIsRejectModalOpen] = useState(false);
  const [targetRejectReviewId, setTargetRejectReviewId] = useState<string | null>(null);
  const [rejectReasonInput, setRejectReasonInput] = useState('');
  const [selectedPresetReason, setSelectedPresetReason] = useState('');
  const [lightboxImage, setLightboxImage] = useState<string | null>(null);
  const [selectedReviewIds, setSelectedReviewIds] = useState<string[]>([]);
  const [auditEvents, setAuditEvents] = useState<AdminProductReviewAuditEvent[]>([]);
  const [isAuditLoading, setIsAuditLoading] = useState(false);

  const pendingCount = useMemo(() => reviews.filter((review) => review.status === 'pending').length, [reviews]);
  const publishedCount = useMemo(() => reviews.filter((review) => review.status === 'published').length, [reviews]);
  const rejectedCount = useMemo(() => reviews.filter((review) => review.status === 'rejected').length, [reviews]);
  const totalCount = reviews.length;

  const avgPublishedRating = useMemo(() => {
    if (publishedCount === 0) return '0.0';
    const totalRating = reviews
      .filter((review) => review.status === 'published')
      .reduce((sum, review) => sum + review.rating, 0);
    return (totalRating / publishedCount).toFixed(1);
  }, [publishedCount, reviews]);

  useEffect(() => {
    if (reviewFilterProductId) {
      setSelectedProductFilter(reviewFilterProductId);
    }
  }, [reviewFilterProductId]);

  const filteredReviews = useMemo(() => reviews.filter((review) => {
    if (activeTab !== 'all' && review.status !== activeTab) return false;
    if (selectedProductFilter !== 'all' && review.productId !== selectedProductFilter) return false;
    if (ratingFilter !== 'all' && review.rating !== Number.parseInt(ratingFilter, 10)) return false;
    if (hasPhotosOnly && review.photos.length === 0) return false;

    if (!searchQuery.trim()) return true;

    const query = searchQuery.trim().toLowerCase();
    return [
      review.productTitle,
      review.buyerName,
      review.orderNumber,
      review.title,
      review.comment,
      review.variantTitle ?? '',
      review.customerEmail,
    ].some((value) => value.toLowerCase().includes(query));
  }), [activeTab, hasPhotosOnly, ratingFilter, reviews, searchQuery, selectedProductFilter]);

  const clearAllFilters = (): void => {
    setSearchQuery('');
    setSelectedProductFilter('all');
    setRatingFilter('all');
    setHasPhotosOnly(false);
    setReviewFilterProductId(null);
  };

  const handleOpenDetail = async (review: AdminProductReview): Promise<void> => {
    setActiveReviewForDrawer(review);
    setSelectedReviewId(review.id);
    setIsDetailOpen(true);

    if (!canViewReviewAudit) {
      setAuditEvents([]);
      return;
    }

    setIsAuditLoading(true);
    try {
      setAuditEvents(await fetchReviewAudit(review.id));
    } finally {
      setIsAuditLoading(false);
    }
  };

  const handleCloseDetail = (): void => {
    setIsDetailOpen(false);
    setActiveReviewForDrawer(null);
    setSelectedReviewId(null);
    setAuditEvents([]);
  };

  const updateDrawerStatus = (reviewId: string, status: AdminProductReview['status'], moderationNote?: string): void => {
    if (activeReviewForDrawer?.id !== reviewId) return;

    const nextPublishedAt = status === 'published' ? new Date().toISOString() : activeReviewForDrawer.publishedAt;
    setActiveReviewForDrawer({
      ...activeReviewForDrawer,
      status,
      moderationNote: moderationNote ?? activeReviewForDrawer.moderationNote,
      publishedAt: nextPublishedAt,
    });
  };

  const runModeration = async (
    reviewId: string,
    action: ReviewModerationAction,
    reason?: string,
  ): Promise<void> => {
    const result = await moderateReview(reviewId, action, reason);
    if (!result) return;

    updateDrawerStatus(reviewId, result.status, reason);

    if (isDetailOpen && canViewReviewAudit) {
      setIsAuditLoading(true);
      try {
        setAuditEvents(await fetchReviewAudit(reviewId));
      } finally {
        setIsAuditLoading(false);
      }
    }
  };

  const handleQuickPublish = async (event: React.MouseEvent, reviewId: string): Promise<void> => {
    event.stopPropagation();
    await runModeration(reviewId, 'publish');
  };

  const handleOpenRejectModal = (event: React.MouseEvent | null, reviewId: string): void => {
    event?.stopPropagation();
    setTargetRejectReviewId(reviewId);
    setRejectReasonInput('');
    setSelectedPresetReason('');
    setIsRejectModalOpen(true);
  };

  const handleConfirmReject = async (): Promise<void> => {
    if (!targetRejectReviewId) return;

    const finalReason = rejectReasonInput.trim() || selectedPresetReason || 'Review did not meet moderation guidelines.';
    await runModeration(targetRejectReviewId, 'reject', finalReason);
    setIsRejectModalOpen(false);
    setTargetRejectReviewId(null);
    setRejectReasonInput('');
    setSelectedPresetReason('');
  };

  const handleUnpublish = async (event: React.MouseEvent | null, reviewId: string): Promise<void> => {
    event?.stopPropagation();
    await runModeration(reviewId, 'reject', 'Unpublished from public storefront by admin.');
  };

  const handleSelectAll = (): void => {
    if (!canBulkModerateReviews || filteredReviews.length === 0) return;

    setSelectedReviewIds((current) => (
      current.length === filteredReviews.length ? [] : filteredReviews.map((review) => review.id)
    ));
  };

  const handleToggleSelectReview = (reviewId: string): void => {
    if (!canBulkModerateReviews) return;

    setSelectedReviewIds((current) => (
      current.includes(reviewId)
        ? current.filter((id) => id !== reviewId)
        : [...current, reviewId]
    ));
  };

  const handleBatchPublish = async (): Promise<void> => {
    if (!canBulkModerateReviews || selectedReviewIds.length === 0) return;
    const result = await bulkModerateReviews(selectedReviewIds, 'publish');
    if (!result) return;
    setSelectedReviewIds([]);
  };

  const handleBatchReject = async (): Promise<void> => {
    if (!canBulkModerateReviews || selectedReviewIds.length === 0) return;
    const result = await bulkModerateReviews(selectedReviewIds, 'reject', 'Batch rejected by moderation staff.');
    if (!result) return;
    setSelectedReviewIds([]);
  };

  return (
    <div id="product-reviews-admin" className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold text-neutral-500 dark:text-neutral-400">
            <span
              onClick={() => setCurrentSection('catalog')}
              className="hover:text-indigo-600 dark:hover:text-indigo-400 cursor-pointer transition-colors"
            >
              Products & Catalog
            </span>
            <ChevronRight className="w-3.5 h-3.5" />
            <span className="text-neutral-900 dark:text-white font-bold">Product Reviews Moderation</span>
          </div>
          <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-neutral-900 dark:text-white mt-1">
            Buyer Reviews Moderation
          </h1>
          <p className="text-xs sm:text-sm text-neutral-500 dark:text-neutral-400 mt-0.5">
            Moderate and approve authentic customer ratings, verified purchase proof, and customer unboxing photos before live storefront publishing.
          </p>
        </div>

        <div className="flex items-center gap-2.5">
          <button
            type="button"
            onClick={() => setCurrentSection('catalog-products')}
            className="px-3 py-2 text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors flex items-center gap-1.5 shadow-xs cursor-pointer"
          >
            <Package className="w-3.5 h-3.5 text-neutral-400" />
            <span>Product Matrix</span>
          </button>
        </div>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <div
          onClick={() => setActiveTab('pending')}
          className={`p-4 rounded-2xl border transition-all cursor-pointer ${
            activeTab === 'pending'
              ? 'bg-amber-50/70 dark:bg-amber-950/30 border-amber-400 dark:border-amber-700 ring-2 ring-amber-400/20'
              : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700'
          }`}
        >
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Awaiting Review</span>
            <div className="w-8 h-8 rounded-xl bg-amber-500/10 dark:bg-amber-500/20 flex items-center justify-center text-amber-600 dark:text-amber-400">
              <Clock className="w-4 h-4" />
            </div>
          </div>
          <div className="flex items-baseline gap-2 mt-2">
            <span className="text-2xl font-bold text-neutral-900 dark:text-white font-mono">{pendingCount}</span>
            {pendingCount > 0 && (
              <span className="text-[10px] font-bold px-1.5 py-0.2 rounded-full bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30 animate-pulse">
                Action Req.
              </span>
            )}
          </div>
          <p className="text-[11px] text-neutral-400 mt-1 truncate">Unpublished buyer submissions</p>
        </div>

        <div
          onClick={() => setActiveTab('published')}
          className={`p-4 rounded-2xl border transition-all cursor-pointer ${
            activeTab === 'published'
              ? 'bg-emerald-50/70 dark:bg-emerald-950/30 border-emerald-400 dark:border-emerald-700 ring-2 ring-emerald-400/20'
              : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700'
          }`}
        >
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Published to PDP</span>
            <div className="w-8 h-8 rounded-xl bg-emerald-500/10 dark:bg-emerald-500/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
              <CheckCircle2 className="w-4 h-4" />
            </div>
          </div>
          <div className="flex items-baseline gap-2 mt-2">
            <span className="text-2xl font-bold text-neutral-900 dark:text-white font-mono">{publishedCount}</span>
            <span className="text-[10px] font-bold px-1.5 py-0.2 rounded-full bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">
              Live
            </span>
          </div>
          <p className="text-[11px] text-neutral-400 mt-1 truncate">Visible on storefront pages</p>
        </div>

        <div
          onClick={() => setActiveTab('rejected')}
          className={`p-4 rounded-2xl border transition-all cursor-pointer ${
            activeTab === 'rejected'
              ? 'bg-rose-50/70 dark:bg-rose-950/30 border-rose-400 dark:border-rose-700 ring-2 ring-rose-400/20'
              : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700'
          }`}
        >
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Rejected / Spam</span>
            <div className="w-8 h-8 rounded-xl bg-rose-500/10 dark:bg-rose-500/20 flex items-center justify-center text-rose-600 dark:text-rose-400">
              <XCircle className="w-4 h-4" />
            </div>
          </div>
          <div className="flex items-baseline gap-2 mt-2">
            <span className="text-2xl font-bold text-neutral-900 dark:text-white font-mono">{rejectedCount}</span>
            <span className="text-[10px] font-bold px-1.5 py-0.2 rounded-full bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-500/30">
              Filtered
            </span>
          </div>
          <p className="text-[11px] text-neutral-400 mt-1 truncate">Internal reason recorded</p>
        </div>

        <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Storefront Average</span>
            <div className="w-8 h-8 rounded-xl bg-indigo-500/10 dark:bg-indigo-500/20 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
              <Star className="w-4 h-4 fill-indigo-600 dark:fill-indigo-400" />
            </div>
          </div>
          <div className="flex items-baseline gap-2 mt-2">
            <span className="text-2xl font-bold text-neutral-900 dark:text-white font-mono">{avgPublishedRating} ★</span>
            <span className="text-xs font-bold text-amber-500">★★★★★</span>
          </div>
          <p className="text-[11px] text-neutral-400 mt-1 truncate">Across {publishedCount} live reviews</p>
        </div>
      </div>

      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs overflow-hidden">
        <div className="flex flex-wrap items-center justify-between gap-3 px-4 pt-4 pb-3 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-900/50">
          <div className="flex items-center gap-1.5 overflow-x-auto">
            <button
              type="button"
              onClick={() => setActiveTab('pending')}
              className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer ${
                activeTab === 'pending'
                  ? 'bg-amber-500 text-white shadow-sm'
                  : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800'
              }`}
            >
              <Clock className="w-3.5 h-3.5" />
              <span>Pending Moderation</span>
              {pendingCount > 0 && (
                <span className={`text-[10px] font-bold px-1.5 py-0.2 rounded-full ${activeTab === 'pending' ? 'bg-black/20 text-white' : 'bg-amber-500/15 text-amber-700 dark:text-amber-300'}`}>
                  {pendingCount}
                </span>
              )}
            </button>

            <button
              type="button"
              onClick={() => setActiveTab('published')}
              className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer ${
                activeTab === 'published'
                  ? 'bg-emerald-600 text-white shadow-sm'
                  : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800'
              }`}
            >
              <CheckCircle2 className="w-3.5 h-3.5" />
              <span>Published & Live</span>
              <span className={`text-[10px] font-bold px-1.5 py-0.2 rounded-full ${activeTab === 'published' ? 'bg-white/20 text-white' : 'bg-neutral-200 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400'}`}>
                {publishedCount}
              </span>
            </button>

            <button
              type="button"
              onClick={() => setActiveTab('rejected')}
              className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer ${
                activeTab === 'rejected'
                  ? 'bg-rose-600 text-white shadow-sm'
                  : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800'
              }`}
            >
              <XCircle className="w-3.5 h-3.5" />
              <span>Rejected</span>
              <span className={`text-[10px] font-bold px-1.5 py-0.2 rounded-full ${activeTab === 'rejected' ? 'bg-white/20 text-white' : 'bg-neutral-200 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400'}`}>
                {rejectedCount}
              </span>
            </button>

            <button
              type="button"
              onClick={() => setActiveTab('all')}
              className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer ${
                activeTab === 'all'
                  ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 shadow-sm'
                  : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800'
              }`}
            >
              <span>All ({totalCount})</span>
            </button>
          </div>

          {selectedProductFilter !== 'all' && (
            <div className="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-xs">
              <Package className="w-3.5 h-3.5" />
              <span className="font-semibold truncate max-w-[200px]">
                Product: {products.find((product) => product.id === selectedProductFilter)?.title || selectedProductFilter}
              </span>
              <button
                type="button"
                onClick={() => {
                  setSelectedProductFilter('all');
                  setReviewFilterProductId(null);
                }}
                className="p-0.5 hover:bg-indigo-200 dark:hover:bg-indigo-800 rounded transition cursor-pointer"
                title="Clear Product Filter"
              >
                <X className="w-3 h-3" />
              </button>
            </div>
          )}
        </div>

        <div className="p-4 grid grid-cols-1 sm:grid-cols-12 gap-3 border-b border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900">
          <div className="sm:col-span-4 relative">
            <Search className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-400" />
            <input
              type="text"
              value={searchQuery}
              onChange={(event) => setSearchQuery(event.target.value)}
              placeholder="Search by buyer name, order #, review..."
              className="w-full pl-9 pr-3.5 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950 text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition"
            />
          </div>

          <div className="sm:col-span-3">
            <select
              value={selectedProductFilter}
              onChange={(event) => {
                setSelectedProductFilter(event.target.value);
                setReviewFilterProductId(event.target.value === 'all' ? null : event.target.value);
              }}
              className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950 text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition cursor-pointer"
            >
              <option value="all">All Products ({products.length})</option>
              {products.map((product) => (
                <option key={product.id} value={product.id}>
                  {product.title}
                </option>
              ))}
            </select>
          </div>

          <div className="sm:col-span-2">
            <select
              value={ratingFilter}
              onChange={(event) => setRatingFilter(event.target.value)}
              className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950 text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition cursor-pointer"
            >
              <option value="all">All Ratings</option>
              <option value="5">5 Stars (★★★★★)</option>
              <option value="4">4 Stars (★★★★☆)</option>
              <option value="3">3 Stars (★★★☆☆)</option>
              <option value="2">2 Stars (★★☆☆☆)</option>
              <option value="1">1 Star (★☆☆☆☆)</option>
            </select>
          </div>

          <div className="sm:col-span-2 flex items-center">
            <button
              type="button"
              onClick={() => setHasPhotosOnly(!hasPhotosOnly)}
              className={`w-full px-3 py-2 rounded-xl text-xs font-semibold border flex items-center justify-center gap-1.5 transition cursor-pointer ${
                hasPhotosOnly
                  ? 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800 font-bold'
                  : 'border-neutral-200 dark:border-neutral-800 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800'
              }`}
            >
              <ImageIcon className="w-3.5 h-3.5" />
              <span>With Photos</span>
            </button>
          </div>

          {(searchQuery || selectedProductFilter !== 'all' || ratingFilter !== 'all' || hasPhotosOnly) && (
            <div className="sm:col-span-1 flex items-center justify-end">
              <button
                type="button"
                onClick={clearAllFilters}
                className="px-2.5 py-2 text-xs text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded-xl transition font-semibold"
                title="Reset all filters"
              >
                Reset
              </button>
            </div>
          )}
        </div>

        {canBulkModerateReviews && selectedReviewIds.length > 0 && (
          <div className="px-4 py-2.5 bg-indigo-50 dark:bg-indigo-950/50 border-b border-indigo-100 dark:border-indigo-900/60 flex items-center justify-between gap-3 text-xs animate-in fade-in">
            <div className="flex items-center gap-2 text-indigo-900 dark:text-indigo-200 font-semibold">
              <Check className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
              <span>{selectedReviewIds.length} reviews selected</span>
            </div>

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => void handleBatchPublish()}
                className="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-xs transition"
              >
                <Check className="w-3.5 h-3.5" />
                <span>Approve & Publish Selected</span>
              </button>

              <button
                type="button"
                onClick={() => void handleBatchReject()}
                className="px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-xs transition"
              >
                <X className="w-3.5 h-3.5" />
                <span>Reject Selected</span>
              </button>

              <button
                type="button"
                onClick={() => setSelectedReviewIds([])}
                className="px-2.5 py-1.5 text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300 text-xs font-semibold"
              >
                Cancel
              </button>
            </div>
          </div>
        )}

        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-950/40 text-[11px] font-bold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                <th className="py-3.5 px-4 w-10 text-center">
                  <input
                    type="checkbox"
                    checked={filteredReviews.length > 0 && selectedReviewIds.length === filteredReviews.length}
                    onChange={handleSelectAll}
                    disabled={!canBulkModerateReviews}
                    className="rounded border-neutral-300 dark:border-neutral-700 text-indigo-600 focus:ring-indigo-500 cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                  />
                </th>
                <th className="py-3.5 px-4">Product & Item</th>
                <th className="py-3.5 px-4">Buyer & Order</th>
                <th className="py-3.5 px-4">Rating & Review</th>
                <th className="py-3.5 px-4">Submitted</th>
                <th className="py-3.5 px-4">Moderation Status</th>
                <th className="py-3.5 px-4 text-right">Quick Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800/80 text-xs">
              {filteredReviews.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-12 px-4 text-center">
                    <div className="max-w-sm mx-auto space-y-3">
                      <div className="w-12 h-12 rounded-2xl bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center mx-auto text-neutral-400">
                        <MessageSquare className="w-6 h-6" />
                      </div>
                      <h4 className="text-sm font-bold text-neutral-900 dark:text-white">
                        {activeTab === 'pending'
                          ? 'No Pending Reviews'
                          : activeTab === 'published'
                          ? 'No Published Reviews'
                          : activeTab === 'rejected'
                          ? 'No Rejected Reviews'
                          : 'No Reviews Found'}
                      </h4>
                      <p className="text-xs text-neutral-500 dark:text-neutral-400">
                        {activeTab === 'pending'
                          ? 'All real customer reviews have been moderated. New buyer submissions post-delivery will appear here automatically.'
                          : 'No customer reviews match your selected filter criteria. Try resetting the filters.'}
                      </p>
                      {(searchQuery || selectedProductFilter !== 'all' || ratingFilter !== 'all' || hasPhotosOnly) && (
                        <button
                          type="button"
                          onClick={clearAllFilters}
                          className="px-3.5 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 text-xs font-bold border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 transition"
                        >
                          Clear Filters
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ) : (
                filteredReviews.map((review) => {
                  const isSelected = selectedReviewIds.includes(review.id);
                  const isRowActive = selectedReviewId === review.id;
                  const photoUrls = reviewPhotoUrls(review);

                  return (
                    <tr
                      key={review.id}
                      onClick={() => void handleOpenDetail(review)}
                      className={`group hover:bg-neutral-50/80 dark:hover:bg-neutral-800/50 transition-colors cursor-pointer ${
                        isRowActive
                          ? 'bg-indigo-50/40 dark:bg-indigo-950/20'
                          : isSelected
                          ? 'bg-indigo-50/20 dark:bg-indigo-950/10'
                          : ''
                      }`}
                    >
                      <td className="py-4 px-4 text-center" onClick={(event) => event.stopPropagation()}>
                        <input
                          type="checkbox"
                          checked={isSelected}
                          onChange={() => handleToggleSelectReview(review.id)}
                          disabled={!canBulkModerateReviews}
                          className="rounded border-neutral-300 dark:border-neutral-700 text-indigo-600 focus:ring-indigo-500 cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                        />
                      </td>

                      <td className="py-4 px-4">
                        <div className="flex items-center gap-3 min-w-[180px] max-w-[240px]">
                          <img
                            src={review.productThumbnail || 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=120'}
                            alt={review.productTitle}
                            className="w-11 h-11 rounded-xl object-cover border border-neutral-200 dark:border-neutral-800 shrink-0 bg-neutral-100 dark:bg-neutral-800"
                            referrerPolicy="no-referrer"
                          />
                          <div className="min-w-0">
                            <h4 className="font-bold text-neutral-900 dark:text-white truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                              {review.productTitle}
                            </h4>
                            {review.variantTitle && (
                              <p className="text-[11px] text-neutral-400 truncate mt-0.5 font-mono">
                                Variant: {review.variantTitle}
                              </p>
                            )}
                          </div>
                        </div>
                      </td>

                      <td className="py-4 px-4">
                        <div className="min-w-[150px]">
                          <div className="flex items-center gap-1.5">
                            <span className="font-bold text-neutral-900 dark:text-white truncate">{review.buyerName}</span>
                            <span
                              className="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded-md bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-[10px] font-bold shrink-0"
                              title="System-verified purchase connected to real completed order"
                            >
                              <ShieldCheck className="w-3 h-3 text-emerald-600 dark:text-emerald-400" />
                              <span>Verified</span>
                            </span>
                          </div>

                          <div className="flex items-center gap-1.5 mt-1 text-[11px] text-neutral-500 dark:text-neutral-400 font-mono">
                            <ShoppingBag className="w-3 h-3 text-neutral-400" />
                            <span>{review.orderNumber}</span>
                          </div>
                        </div>
                      </td>

                      <td className="py-4 px-4">
                        <div className="min-w-[240px] max-w-[340px] space-y-1">
                          <div className="flex items-center gap-1.5">
                            <div className="flex items-center text-amber-500">
                              {Array.from({ length: 5 }).map((_, index) => (
                                <Star
                                  key={index}
                                  className={`w-3.5 h-3.5 ${index < review.rating ? 'fill-amber-400 text-amber-400' : 'text-neutral-300 dark:text-neutral-700'}`}
                                />
                              ))}
                            </div>
                            <span className="text-[11px] font-bold text-neutral-700 dark:text-neutral-300 font-mono">
                              {review.rating}.0
                            </span>
                            <span className="font-bold text-neutral-900 dark:text-white truncate ml-1">
                              {review.title}
                            </span>
                          </div>

                          <p className="text-[11px] text-neutral-600 dark:text-neutral-400 line-clamp-2 leading-relaxed">
                            {review.comment}
                          </p>

                          {photoUrls.length > 0 && (
                            <div className="flex items-center gap-1.5 pt-0.5">
                              <span className="inline-flex items-center gap-1 text-[10px] font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/50 px-1.5 py-0.5 rounded-md border border-indigo-200 dark:border-indigo-800">
                                <ImageIcon className="w-3 h-3" />
                                {photoUrls.length} photo{photoUrls.length > 1 ? 's' : ''}
                              </span>
                              {photoUrls.slice(0, 3).map((photoUrl, photoIndex) => (
                                <img
                                  key={photoIndex}
                                  src={photoUrl}
                                  alt="Buyer photo thumbnail"
                                  onClick={(event) => {
                                    event.stopPropagation();
                                    setLightboxImage(photoUrl);
                                  }}
                                  className="w-6 h-6 rounded-md object-cover border border-neutral-300 dark:border-neutral-700 hover:scale-110 transition-transform"
                                  referrerPolicy="no-referrer"
                                />
                              ))}
                            </div>
                          )}
                        </div>
                      </td>

                      <td className="py-4 px-4 whitespace-nowrap">
                        <div className="text-[11px] text-neutral-700 dark:text-neutral-300 font-mono">
                          {new Date(review.submittedAt).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}
                        </div>
                        <div className="text-[10px] text-neutral-400">
                          {new Date(review.submittedAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        </div>
                      </td>

                      <td className="py-4 px-4 whitespace-nowrap">
                        {review.status === 'pending' && (
                          <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-700/60 font-bold text-[11px]">
                            <Clock className="w-3 h-3" />
                            <span>Pending</span>
                          </span>
                        )}

                        {review.status === 'published' && (
                          <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700/60 font-bold text-[11px]">
                            <CheckCircle2 className="w-3 h-3" />
                            <span>Published</span>
                          </span>
                        )}

                        {review.status === 'rejected' && (
                          <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-300 dark:border-rose-700/60 font-bold text-[11px]">
                            <XCircle className="w-3 h-3" />
                            <span>Rejected</span>
                          </span>
                        )}

                        {review.moderatedBy && (
                          <div className="text-[10px] text-neutral-400 mt-1 truncate max-w-[120px]">
                            by {review.moderatedBy}
                          </div>
                        )}
                      </td>

                      <td className="py-4 px-4 text-right whitespace-nowrap" onClick={(event) => event.stopPropagation()}>
                        <div className="flex items-center justify-end gap-1.5">
                          {canModerateReviews && review.status === 'pending' && (
                            <>
                              <button
                                type="button"
                                onClick={(event) => void handleQuickPublish(event, review.id)}
                                className="px-2.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1 shadow-xs transition cursor-pointer"
                                title="Approve & Publish to PDP"
                              >
                                <Check className="w-3.5 h-3.5" />
                                <span>Approve</span>
                              </button>

                              <button
                                type="button"
                                onClick={(event) => handleOpenRejectModal(event, review.id)}
                                className="px-2.5 py-1.5 rounded-lg bg-white dark:bg-neutral-800 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-neutral-200 dark:border-neutral-700 font-bold text-xs flex items-center gap-1 transition cursor-pointer"
                                title="Reject review"
                              >
                                <X className="w-3.5 h-3.5" />
                                <span>Reject</span>
                              </button>
                            </>
                          )}

                          {canModerateReviews && review.status === 'published' && (
                            <button
                              type="button"
                              onClick={(event) => void handleUnpublish(event, review.id)}
                              className="px-2.5 py-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-400 text-xs font-semibold transition cursor-pointer"
                              title="Unpublish from storefront"
                            >
                              Unpublish
                            </button>
                          )}

                          {canModerateReviews && review.status === 'rejected' && (
                            <button
                              type="button"
                              onClick={(event) => void handleQuickPublish(event, review.id)}
                              className="px-2.5 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 text-xs font-bold transition cursor-pointer"
                              title="Re-evaluate and publish"
                            >
                              Publish
                            </button>
                          )}

                          <button
                            type="button"
                            onClick={() => void handleOpenDetail(review)}
                            className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-700 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
                            title="View full review details & moderation audit"
                          >
                            <Eye className="w-4 h-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>

      {isDetailOpen && activeReviewForDrawer && (
        <div className="fixed inset-0 z-50 overflow-hidden flex justify-end">
          <div
            onClick={handleCloseDetail}
            className="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"
          />

          <div className="relative w-full max-w-xl bg-white dark:bg-neutral-900 border-l border-neutral-200 dark:border-neutral-800 h-full shadow-2xl flex flex-col z-10 animate-in slide-in-from-right duration-200">
            <div className="p-5 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between bg-neutral-50/50 dark:bg-neutral-950/50">
              <div className="flex items-center gap-2.5">
                <div className="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-sm">
                  <Star className="w-5 h-5 fill-white" />
                </div>
                <div>
                  <div className="flex items-center gap-2">
                    <h3 className="font-bold text-sm text-neutral-900 dark:text-white">Review Moderation Panel</h3>
                    {activeReviewForDrawer.status === 'pending' && (
                      <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30">Pending</span>
                    )}
                    {activeReviewForDrawer.status === 'published' && (
                      <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">Published</span>
                    )}
                    {activeReviewForDrawer.status === 'rejected' && (
                      <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-500/30">Rejected</span>
                    )}
                  </div>
                  <p className="text-[11px] text-neutral-400 font-mono">
                    ID: {activeReviewForDrawer.id} • Submitted: {new Date(activeReviewForDrawer.submittedAt).toLocaleString()}
                  </p>
                </div>
              </div>

              <button
                type="button"
                onClick={handleCloseDetail}
                className="p-2 rounded-xl text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-5 space-y-5">
              <div className="p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 flex items-start gap-3.5">
                <img
                  src={activeReviewForDrawer.productThumbnail || 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=120'}
                  alt={activeReviewForDrawer.productTitle}
                  className="w-16 h-16 rounded-xl object-cover border border-neutral-200 dark:border-neutral-800 shrink-0"
                  referrerPolicy="no-referrer"
                />
                <div className="min-w-0 flex-1">
                  <span className="text-[10px] uppercase tracking-wider font-bold text-neutral-400 font-mono">
                    Target SKU Record
                  </span>
                  <h4 className="font-bold text-sm text-neutral-900 dark:text-white truncate">
                    {activeReviewForDrawer.productTitle}
                  </h4>
                  {activeReviewForDrawer.variantTitle && (
                    <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 font-mono">
                      Variant: {activeReviewForDrawer.variantTitle}
                    </p>
                  )}
                  <div className="mt-2 flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => {
                        handleCloseDetail();
                        setCurrentSection('catalog-products');
                      }}
                      className="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
                    >
                      <span>View in Product Matrix</span>
                      <ExternalLink className="w-3 h-3" />
                    </button>
                  </div>
                </div>
              </div>

              <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-3">
                <div className="flex items-center justify-between">
                  <h4 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                    <ShieldCheck className="w-4 h-4 text-emerald-600" />
                    <span>Purchase Evidence & Order Metadata</span>
                  </h4>
                  {activeReviewForDrawer.verifiedBuyer && (
                    <span className="px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-bold text-[10px]">
                      ✓ Verified Buyer Match
                    </span>
                  )}
                </div>

                <div className="grid grid-cols-2 gap-3 text-xs">
                  <div className="p-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200/60 dark:border-neutral-800/60">
                    <span className="text-[10px] text-neutral-400 block font-medium">Customer Display Name</span>
                    <span className="font-bold text-neutral-900 dark:text-white mt-0.5 block">{activeReviewForDrawer.buyerName}</span>
                  </div>
                  <div className="p-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200/60 dark:border-neutral-800/60">
                    <span className="text-[10px] text-neutral-400 block font-medium">Associated Order #</span>
                    <span className="font-bold text-indigo-600 dark:text-indigo-400 mt-0.5 block font-mono">{activeReviewForDrawer.orderNumber}</span>
                  </div>
                </div>
              </div>

              <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-1.5">
                    <div className="flex items-center text-amber-400">
                      {Array.from({ length: 5 }).map((_, index) => (
                        <Star
                          key={index}
                          className={`w-4 h-4 ${index < activeReviewForDrawer.rating ? 'fill-amber-400 text-amber-400' : 'text-neutral-300 dark:text-neutral-700'}`}
                        />
                      ))}
                    </div>
                    <span className="font-bold text-sm text-neutral-900 dark:text-white font-mono">
                      {activeReviewForDrawer.rating}.0 / 5.0
                    </span>
                  </div>

                  <span className="text-[11px] text-neutral-400 font-mono">Helpful Votes: 0</span>
                </div>

                <div className="pt-2">
                  <h4 className="font-bold text-base text-neutral-900 dark:text-white">{activeReviewForDrawer.title}</h4>
                  <p className="text-xs text-neutral-700 dark:text-neutral-300 mt-2 leading-relaxed whitespace-pre-wrap">
                    {activeReviewForDrawer.comment}
                  </p>
                </div>

                {reviewPhotoUrls(activeReviewForDrawer).length > 0 && (
                  <div className="pt-3 border-t border-neutral-100 dark:border-neutral-800">
                    <h5 className="text-[11px] font-bold text-neutral-700 dark:text-neutral-300 mb-2 flex items-center gap-1.5">
                      <ImageIcon className="w-3.5 h-3.5 text-indigo-500" />
                      <span>Customer Attached Media ({reviewPhotoUrls(activeReviewForDrawer).length})</span>
                    </h5>
                    <div className="grid grid-cols-3 gap-2">
                      {reviewPhotoUrls(activeReviewForDrawer).map((photoUrl, index) => (
                        <div
                          key={index}
                          onClick={() => setLightboxImage(photoUrl)}
                          className="group relative rounded-xl overflow-hidden aspect-square border border-neutral-200 dark:border-neutral-800 cursor-pointer"
                        >
                          <img
                            src={photoUrl}
                            alt={`Review photo ${index + 1}`}
                            className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                            referrerPolicy="no-referrer"
                          />
                          <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white">
                            <Maximize2 className="w-4 h-4" />
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>

              {(activeReviewForDrawer.status === 'rejected' || activeReviewForDrawer.moderationNote) && (
                <div className="p-4 rounded-2xl bg-rose-50/80 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/80 space-y-1.5">
                  <div className="flex items-center gap-1.5 text-rose-800 dark:text-rose-300 font-bold text-xs">
                    <AlertCircle className="w-4 h-4 text-rose-600 dark:text-rose-400" />
                    <span>Internal Rejection Note & Audit</span>
                  </div>
                  <p className="text-xs text-rose-900/90 dark:text-rose-200 leading-relaxed font-mono">
                    {activeReviewForDrawer.moderationNote || 'Rejected during compliance moderation.'}
                  </p>
                  {activeReviewForDrawer.moderatedBy && (
                    <p className="text-[10px] text-rose-700 dark:text-rose-400 mt-1">
                      Moderated by: {activeReviewForDrawer.moderatedBy}
                    </p>
                  )}
                </div>
              )}

              {activeReviewForDrawer.status === 'published' && activeReviewForDrawer.moderatedBy && (
                <div className="p-3.5 rounded-2xl bg-emerald-50/80 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/80 flex items-center gap-2 text-xs text-emerald-800 dark:text-emerald-300">
                  <CheckCircle2 className="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                  <div>
                    <span className="font-bold">Approved and published to Storefront PDP.</span>
                    <p className="text-[10px] text-emerald-700 dark:text-emerald-400 mt-0.5">
                      Moderated by {activeReviewForDrawer.moderatedBy} on {activeReviewForDrawer.publishedAt ? new Date(activeReviewForDrawer.publishedAt).toLocaleString() : 'Recently'}
                    </p>
                  </div>
                </div>
              )}

              {canViewReviewAudit && (
                <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-3">
                  <div className="flex items-center justify-between">
                    <h4 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                      <ShieldCheck className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                      <span>Moderation Audit History</span>
                    </h4>
                    {isAuditLoading && <span className="text-[10px] text-neutral-400 font-mono">Loading...</span>}
                  </div>

                  {auditEvents.length === 0 && !isAuditLoading ? (
                    <p className="text-xs text-neutral-500 dark:text-neutral-400">
                      No moderation audit events recorded for this review yet.
                    </p>
                  ) : (
                    <div className="space-y-2">
                      {auditEvents.map((event) => (
                        <div
                          key={event.id}
                          className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200/60 dark:border-neutral-800/60"
                        >
                          <div className="flex items-center justify-between gap-3">
                            <span className="text-[11px] font-bold text-neutral-900 dark:text-white uppercase tracking-wide">
                              {auditEventSummary(event)}
                            </span>
                            <span className="text-[10px] text-neutral-400 font-mono">
                              {event.occurredAt ? new Date(event.occurredAt).toLocaleString() : 'Recently'}
                            </span>
                          </div>
                          <p className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-1">
                            {event.actorName || 'System'} • {event.fromStatus || 'none'} {'->'} {event.toStatus || 'none'}
                          </p>
                          {typeof event.metadata.reason === 'string' && event.metadata.reason.length > 0 && (
                            <p className="text-[11px] text-neutral-700 dark:text-neutral-300 mt-2 font-mono whitespace-pre-wrap">
                              {event.metadata.reason}
                            </p>
                          )}
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </div>

            <div className="p-4 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-950/50 flex items-center justify-between gap-3">
              <button
                type="button"
                onClick={handleCloseDetail}
                className="px-4 py-2.5 rounded-xl border border-neutral-200 dark:border-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-semibold hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
              >
                Close Drawer
              </button>

              <div className="flex items-center gap-2">
                {canModerateReviews && activeReviewForDrawer.status === 'pending' && (
                  <>
                    <button
                      type="button"
                      onClick={(event) => handleOpenRejectModal(event, activeReviewForDrawer.id)}
                      className="px-4 py-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 hover:bg-rose-100 text-xs font-bold flex items-center gap-1.5 transition cursor-pointer"
                    >
                      <X className="w-3.5 h-3.5" />
                      <span>Reject Review</span>
                    </button>

                    <button
                      type="button"
                      onClick={(event) => void handleQuickPublish(event, activeReviewForDrawer.id)}
                      className="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm flex items-center gap-1.5 transition cursor-pointer"
                    >
                      <Check className="w-3.5 h-3.5" />
                      <span>Approve & Publish to PDP</span>
                    </button>
                  </>
                )}

                {canModerateReviews && activeReviewForDrawer.status === 'published' && (
                  <button
                    type="button"
                    onClick={(event) => void handleUnpublish(event, activeReviewForDrawer.id)}
                    className="px-4 py-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 hover:bg-rose-100 text-xs font-bold flex items-center gap-1.5 transition cursor-pointer"
                  >
                    <XCircle className="w-3.5 h-3.5" />
                    <span>Unpublish Review</span>
                  </button>
                )}

                {canModerateReviews && activeReviewForDrawer.status === 'rejected' && (
                  <button
                    type="button"
                    onClick={(event) => void handleQuickPublish(event, activeReviewForDrawer.id)}
                    className="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm flex items-center gap-1.5 transition cursor-pointer"
                  >
                    <Check className="w-3.5 h-3.5" />
                    <span>Re-evaluate & Publish to PDP</span>
                  </button>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {isRejectModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div
            onClick={() => setIsRejectModalOpen(false)}
            className="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"
          />

          <div className="relative w-full max-w-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 shadow-2xl z-10 space-y-4 animate-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2.5">
                <div className="w-9 h-9 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                  <XCircle className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="text-base font-bold text-neutral-900 dark:text-white">Reject Customer Review</h3>
                  <p className="text-xs text-neutral-500 dark:text-neutral-400">
                    Record internal reason for audit trail before discarding.
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setIsRejectModalOpen(false)}
                className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-900 dark:hover:text-white"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="space-y-3">
              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">
                  Preset Rejection Presets (Click to Auto-fill)
                </label>
                <div className="space-y-1.5">
                  {REJECTION_PRESETS.map((preset) => (
                    <button
                      key={preset}
                      type="button"
                      onClick={() => {
                        setSelectedPresetReason(preset);
                        setRejectReasonInput(preset);
                      }}
                      className={`w-full text-left p-2.5 rounded-xl border text-xs transition cursor-pointer flex items-center justify-between ${
                        selectedPresetReason === preset
                          ? 'bg-rose-50 dark:bg-rose-950/40 border-rose-400 text-rose-800 dark:text-rose-300 font-bold'
                          : 'border-neutral-200 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-800/50 text-neutral-700 dark:text-neutral-300'
                      }`}
                    >
                      <span>{preset}</span>
                      {selectedPresetReason === preset && <Check className="w-3.5 h-3.5 text-rose-600 shrink-0 ml-2" />}
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Custom Internal Moderation Note
                </label>
                <textarea
                  rows={3}
                  value={rejectReasonInput}
                  onChange={(event) => setRejectReasonInput(event.target.value)}
                  placeholder="Specify details or instructions for audit record..."
                  className="w-full px-3.5 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-950 border-neutral-200 dark:border-neutral-800 text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500"
                />
              </div>
            </div>

            <div className="flex items-center justify-end gap-2.5 pt-2">
              <button
                type="button"
                onClick={() => setIsRejectModalOpen(false)}
                className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-semibold hover:bg-neutral-100 dark:hover:bg-neutral-800"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={() => void handleConfirmReject()}
                className="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-sm transition"
              >
                Confirm Rejection
              </button>
            </div>
          </div>
        </div>
      )}

      {lightboxImage && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md">
          <div className="relative max-w-3xl max-h-[85vh] flex flex-col items-center">
            <button
              type="button"
              onClick={() => setLightboxImage(null)}
              className="absolute -top-10 right-0 p-2 rounded-full bg-white/10 hover:bg-white/20 text-white transition"
            >
              <X className="w-5 h-5" />
            </button>
            <img
              src={lightboxImage}
              alt="Buyer customer full preview"
              className="max-w-full max-h-[80vh] rounded-2xl object-contain shadow-2xl border border-white/10"
              referrerPolicy="no-referrer"
            />
          </div>
        </div>
      )}
    </div>
  );
};

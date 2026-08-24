import type { Category, CmsPage, Collection, MenuLinkType } from '../types';

export interface MenuTargetOption {
  id: string;
  label: string;
  url: string;
}

type LinkDraft = Pick<{ linkType: MenuLinkType; targetId?: string; url: string }, 'linkType' | 'targetId' | 'url'>;

export const isInternalMenuLinkType = (linkType: MenuLinkType): linkType is Extract<MenuLinkType, 'category' | 'collection' | 'page'> =>
  linkType === 'category' || linkType === 'collection' || linkType === 'page';

export const buildMenuTargetOptions = (
  linkType: MenuLinkType,
  categories: Pick<Category, 'id' | 'name' | 'slug' | 'status'>[],
  collections: Pick<Collection, 'id' | 'title' | 'slug' | 'status'>[],
  pages: Pick<CmsPage, 'id' | 'title' | 'slug' | 'status'>[],
): MenuTargetOption[] => {
  if (linkType === 'category') {
    return categories.filter(category => category.status === 'active').map(category => ({ id: category.id, label: category.name, url: `/categories/${category.slug}` }));
  }
  if (linkType === 'collection') {
    return collections.filter(collection => collection.status === 'active').map(collection => ({ id: collection.id, label: collection.title, url: `/collections/${collection.slug}` }));
  }
  if (linkType === 'page') {
    return pages.filter(page => page.status === 'published').map(page => ({ id: page.id, label: page.title, url: `/pages/${page.slug}` }));
  }
  return [];
};

export const changeMenuLinkType = <T extends LinkDraft>(item: T, linkType: MenuLinkType): T => ({
  ...item,
  linkType,
  ...(isInternalMenuLinkType(linkType) ? { targetId: undefined, url: '' } : { targetId: undefined }),
});

export const selectMenuTarget = <T extends LinkDraft>(item: T, target: MenuTargetOption): T => ({
  ...item,
  targetId: target.id,
  url: target.url,
});

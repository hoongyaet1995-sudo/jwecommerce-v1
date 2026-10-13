import {ShellDesignEditor} from '../menus/ShellDesignEditor';
import {StorefrontHeaderAnnouncementEditor} from './StorefrontHeaderAnnouncementEditor';
export function HeaderFooterAdmin(){return <div className="p-4 md:p-8 space-y-6" data-header-footer-admin><ShellDesignEditor/><details className="rounded-xl border border-neutral-200 dark:border-neutral-800 p-5"><summary className="cursor-pointer text-sm font-semibold">Announcement content</summary><StorefrontHeaderAnnouncementEditor announcementOnly/></details></div>}

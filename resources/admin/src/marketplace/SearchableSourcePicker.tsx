import React,{useRef} from 'react';
import {Folder,FolderArchive,ChevronDown,Search,Check} from 'lucide-react';
type Category={id:string;name:string;slug:string;productCount?:number;disabled?:boolean};
type Collection={id:string;title:string;slug:string;productCount?:number};
// Copied from Collection Tabs SearchableSourcePicker; IDs replace item counts.
interface SearchableSourcePickerProps {
  sourceType: 'category' | 'collection';
  sourceId: string;
  onChange: (sourceId: string, autoLabel?: string) => void;
  categories: Category[];
  collections: Collection[];
}

export const SearchableSourcePicker: React.FC<SearchableSourcePickerProps> = ({
  sourceType,
  sourceId,
  onChange,
  categories,
  collections
}) => {
  const [isOpen, setIsOpen] = React.useState(false);
  const [search, setSearch] = React.useState('');
  const pickerRef = useRef<HTMLDivElement>(null);

  React.useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (pickerRef.current && !pickerRef.current.contains(e.target as Node)) setIsOpen(false);
    };
    if (isOpen) document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [isOpen]);

  const selectedCategory = categories.find(c => c.id === sourceId);
  const selectedCollection = collections.find(c => c.id === sourceId);
  const filteredCategories = categories.filter(c =>
    c.name.toLowerCase().includes(search.toLowerCase()) || c.slug.toLowerCase().includes(search.toLowerCase()) || c.id.includes(search.trim())
  );
  const filteredCollections = collections.filter(c =>
    c.title.toLowerCase().includes(search.toLowerCase()) || c.slug.toLowerCase().includes(search.toLowerCase()) || c.id.includes(search.trim())
  );

  return (
    <div className="relative" ref={pickerRef} onKeyDown={e=>{if(e.key==='Escape')setIsOpen(false);}}>
      <button
        type="button"
        aria-label="Marketplace category" aria-expanded={isOpen}
        onClick={() => {setSearch('');setIsOpen(!isOpen);}}
        className={`w-full flex items-center justify-between px-2.5 py-2 rounded-xl border text-xs font-medium transition-all text-left cursor-pointer ${
          sourceId
            ? 'border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white hover:border-indigo-400'
            : 'border-amber-300 dark:border-amber-700/80 bg-amber-50/50 dark:bg-amber-950/20 text-amber-700 dark:text-amber-300'
        }`}
      >
        <div className="flex items-center gap-2 truncate min-w-0">
          {sourceType === 'category' ? (
            <Folder className="w-3.5 h-3.5 text-indigo-500 shrink-0" />
          ) : (
            <FolderArchive className="w-3.5 h-3.5 text-purple-500 shrink-0" />
          )}
          <span className="truncate">
            {sourceType === 'category'
              ? selectedCategory?.name || 'Select Category...'
              : selectedCollection?.title || 'Select Collection...'}
          </span>
        </div>
        <div className="flex items-center gap-1.5 shrink-0 ml-1">
          {sourceType === 'category' && selectedCategory && (
            <span className="text-[9px] px-1.5 py-0.5 rounded bg-neutral-200/60 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300 font-mono">
              {selectedCategory.id}
            </span>
          )}
          {sourceType === 'collection' && selectedCollection && (
            <span className="text-[9px] px-1.5 py-0.5 rounded bg-neutral-200/60 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300 font-mono">
              {selectedCollection.productCount || 0} items
            </span>
          )}
          <ChevronDown className={`w-3.5 h-3.5 text-neutral-400 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
        </div>
      </button>

      {isOpen && (
        <div className="absolute left-0 right-0 top-full mt-1.5 z-50 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shadow-xl p-2 space-y-1.5 max-h-60 overflow-y-auto animate-in fade-in-50 duration-100">
          <div className="relative">
            <Search className="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-neutral-400" />
            <input
              type="text"
              aria-label="Search marketplace categories"
              autoFocus
              value={search}
              onChange={e => setSearch(e.target.value)}
              placeholder={sourceType === 'category' ? 'Search category name or ID...' : 'Search collections...'}
              className="w-full pl-8 pr-2.5 py-1 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
          </div>

          <div className="space-y-0.5 max-h-40 overflow-y-auto pr-0.5">
            {sourceType === 'category' ? (
              filteredCategories.length > 0 ? (
                filteredCategories.map(cat => {
                  const isSelected = cat.id === sourceId;
                  return (
                    <button
                      key={cat.id}
                      disabled={cat.disabled}
                      type="button"
                      onClick={() => { onChange(cat.id, cat.name); setIsOpen(false); setSearch(''); }}
                      className={`w-full flex items-center justify-between px-2 py-1.5 rounded-lg text-xs transition-colors text-left cursor-pointer ${
                        isSelected
                          ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-semibold'
                          : 'hover:bg-neutral-100 dark:hover:bg-neutral-800/70 text-neutral-700 dark:text-neutral-300'
                      }`}
                    >
                      <div className="flex items-center gap-2 truncate">
                        <Folder className="w-3.5 h-3.5 text-neutral-400 shrink-0" />
                        <span className="truncate">{cat.name}</span>
                      </div>
                      <div className="flex items-center gap-1 shrink-0 ml-2">
                        <span className="text-[10px] text-neutral-400 font-mono">{cat.id}</span>
                        {isSelected && <Check className="w-3.5 h-3.5 text-indigo-500" />}
                      </div>
                    </button>
                  );
                })
              ) : <div className="py-3 text-center text-[11px] text-neutral-400">No matching categories found</div>
            ) : (
              filteredCollections.length > 0 ? (
                filteredCollections.map(col => {
                  const isSelected = col.id === sourceId;
                  return (
                    <button
                      key={col.id}
                      type="button"
                      onClick={() => { onChange(col.id, col.title); setIsOpen(false); setSearch(''); }}
                      className={`w-full flex items-center justify-between px-2 py-1.5 rounded-lg text-xs transition-colors text-left cursor-pointer ${
                        isSelected
                          ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-semibold'
                          : 'hover:bg-neutral-100 dark:hover:bg-neutral-800/70 text-neutral-700 dark:text-neutral-300'
                      }`}
                    >
                      <div className="flex items-center gap-2 truncate">
                        <FolderArchive className="w-3.5 h-3.5 text-neutral-400 shrink-0" />
                        <span className="truncate">{col.title}</span>
                      </div>
                      <div className="flex items-center gap-1 shrink-0 ml-2">
                        <span className="text-[10px] text-neutral-400 font-mono">{col.productCount || 0}</span>
                        {isSelected && <Check className="w-3.5 h-3.5 text-indigo-500" />}
                      </div>
                    </button>
                  );
                })
              ) : <div className="py-3 text-center text-[11px] text-neutral-400">No matching collections found</div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};


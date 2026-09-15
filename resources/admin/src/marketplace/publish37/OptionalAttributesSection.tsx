import React, { useState, useMemo, useRef, useEffect } from 'react';
import {
  ChevronDown,
  ChevronUp,
  Search,
  X,
  Plus,
  Sliders,
  Check,
  HelpCircle,
  Sparkles,
  Info,
  RefreshCw,
  AlertCircle
} from 'lucide-react';

export type AttributeInputType =
  | 'single_select'
  | 'multi_select'
  | 'text'
  | 'number';

export interface AttributeOption {
  id: string;
  label: string;
  value: string;
}

export interface PlatformAttributeMeta {
  id: string;
  name: string;
  inputType: AttributeInputType;
  description?: string;
  allowCustom?: boolean; // Only if true does this field permit custom values
  options?: AttributeOption[];
  units?: string[]; // E.g. ['mAh', 'Wh'] or ['m', 'cm']
  defaultUnit?: string;
  placeholder?: string;
  dependsOn?: {
    attributeId: string;
    conditionValue: string | string[]; // Show only when parent matches
  };
}

export interface OptionalAttributeValues {
  [attributeId: string]: any; // string, string[], or { value: string; unit: string }
}

interface OptionalAttributesSectionProps {
  attributesMeta?: PlatformAttributeMeta[];
  supplemental?:(id:string)=>React.ReactNode;
  values: OptionalAttributeValues;
  onChange: (attributeId: string, val: any) => void;
}

export const OptionalAttributesSection: React.FC<OptionalAttributesSectionProps> = ({
  attributesMeta = [],
  values,
  onChange,
  supplemental
}) => {
  // Collapsible toggle
  const [isExpanded, setIsExpanded] = useState<boolean>(true);

  // Active open dropdown tracker
  const [openDropdownId, setOpenDropdownId] = useState<string | null>(null);

  // Mode for single-select fields with allowCustom: 'select' | 'custom'
  const [customModeFields, setCustomModeFields] = useState<Record<string, boolean>>({});

  // Filter visible attributes based on parent dependencies
  const visibleAttributes = useMemo(() => {
    return attributesMeta.filter(attr => {
      if (!attr.dependsOn) return true;
      const parentVal = values[attr.dependsOn.attributeId];
      if (!parentVal) return false;

      if (Array.isArray(attr.dependsOn.conditionValue)) {
        return attr.dependsOn.conditionValue.includes(parentVal);
      }
      return parentVal === attr.dependsOn.conditionValue;
    });
  }, [attributesMeta, values]);

  // Calculate filled count
  const filledCount = useMemo(() => {
    let count = 0;
    visibleAttributes.forEach(attr => {
      const val = values[attr.id];
      if (val !== undefined && val !== null && val !== '') {
        if (Array.isArray(val) && val.length > 0) count++;
        else if (typeof val === 'object' && val.value) count++;
        else if (typeof val === 'string' && val.trim()) count++;
        else if (typeof val === 'number') count++;
      }
    });
    return count;
  }, [visibleAttributes, values]);

  return (
    <div
      id="optional-attributes-section"
      className="border border-neutral-200 dark:border-neutral-800 rounded-2xl overflow-visible bg-white dark:bg-neutral-900/60 transition-all"
    >
      {/* Header Bar with Accordion Toggle & Filled Count Badge */}
      <button
        type="button"
        onClick={() => setIsExpanded(prev => !prev)}
        className="w-full px-5 py-3.5 flex items-center justify-between bg-neutral-50/70 dark:bg-neutral-800/40 hover:bg-neutral-100/70 dark:hover:bg-neutral-800/70 transition-colors text-left cursor-pointer rounded-t-2xl"
      >
        <div className="flex items-center gap-2.5">
          <div className="w-7 h-7 rounded-lg bg-neutral-200/70 dark:bg-neutral-700/60 flex items-center justify-center text-neutral-700 dark:text-neutral-300">
            <Sliders className="w-3.5 h-3.5" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <span className="text-xs font-bold text-neutral-900 dark:text-white">
                Optional Attributes
              </span>
              <span className="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300">
                {filledCount} of {visibleAttributes.length} filled
              </span>
            </div>
            <p className="text-[11px] text-neutral-500 dark:text-neutral-400">
              Increase marketplace search discovery. Not mandatory to submit.
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2 text-neutral-400">
          <span className="text-xs font-medium hidden sm:inline">
            {isExpanded ? 'Collapse' : 'Expand Form'}
          </span>
          {isExpanded ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
        </div>
      </button>

      {/* Expandable Form Body: 2-Column Grid on desktop, 1-Column on mobile */}
      {isExpanded && (
        <div className="p-5 border-t border-neutral-200 dark:border-neutral-800 space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {visibleAttributes.map(attr => {
              const currentValue = values[attr.id];
              const isCustomMode = customModeFields[attr.id] || false;

              return (
                <div key={attr.id} className="space-y-1.5 relative">
                  {/* Field Label + Custom Mode Switcher */}
                  <div className="flex items-center justify-between">
                    <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300 flex items-center gap-1.5">
                      <span>{attr.name}</span>
                      {attr.dependsOn && (
                        <span className="text-[10px] font-medium text-indigo-500 bg-indigo-50 dark:bg-indigo-950/40 px-1.5 py-0.2 rounded">
                          Dependent
                        </span>
                      )}
                    </label>

                    {/* Single-select custom value toggle (Only if platform allows custom) */}
                    {attr.inputType === 'single_select' && attr.allowCustom && (
                      <button
                        type="button"
                        onClick={() => {
                          setCustomModeFields(prev => ({
                            ...prev,
                            [attr.id]: !prev[attr.id]
                          }));
                          if (openDropdownId === attr.id) setOpenDropdownId(null);
                        }}
                        className="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer"
                      >
                        {isCustomMode ? '← Pick standard option' : '+ Custom value'}
                      </button>
                    )}
                  </div>

                  {/* Field Input Variant Handling */}

                  {/* 1. Free-form text input */}
                  {attr.inputType === 'text' && (
                    <input
                      type="text"
                      aria-label={`Custom ${attr.name}`}
                      value={currentValue || ''}
                      onChange={e => onChange(attr.id, e.target.value)}
                      placeholder={attr.placeholder || 'Enter value...'}
                      className="w-full px-3 py-2 bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs text-neutral-900 dark:text-white placeholder-neutral-400 focus:ring-1 focus:ring-indigo-500"
                    />
                  )}

                  {/* 2. Number input with optional platform unit selector */}
                  {attr.inputType === 'number' && (
                    <div className="flex items-center gap-2">
                      <input
                        type="number"
                        value={typeof currentValue === 'object' ? currentValue.value : currentValue || ''}
                        onChange={e => {
                          if (attr.units && attr.units.length > 0) {
                            const unit = typeof currentValue === 'object' && currentValue.unit ? currentValue.unit : attr.defaultUnit || attr.units[0];
                            onChange(attr.id, { value: e.target.value, unit });
                          } else {
                            onChange(attr.id, e.target.value);
                          }
                        }}
                        placeholder={attr.placeholder || '0'}
                        className="flex-1 px-3 py-2 bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs text-neutral-900 dark:text-white placeholder-neutral-400 font-mono focus:ring-1 focus:ring-indigo-500"
                      />

                      {attr.units && attr.units.length > 0 && (
                        <select
                          value={typeof currentValue === 'object' && currentValue.unit ? currentValue.unit : attr.defaultUnit || attr.units[0]}
                          onChange={e => {
                            const val = typeof currentValue === 'object' ? currentValue.value : currentValue || '';
                            onChange(attr.id, { value: val, unit: e.target.value });
                          }}
                          className="px-2.5 py-2 bg-neutral-100 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs text-neutral-800 dark:text-neutral-200 font-semibold focus:ring-1 focus:ring-indigo-500 shrink-0"
                        >
                          {attr.units.map(u => (
                            <option key={u} value={u}>
                              {u}
                            </option>
                          ))}
                        </select>
                      )}
                    </div>
                  )}

                  {/* 3. Single-Select Field */}
                  {attr.inputType === 'single_select' && (
                    <>
                      {isCustomMode ? (
                        /* Direct custom input if user toggled to custom value */
                        <div className="relative">
                          <input
                            type="text"
                            aria-label={`Custom ${attr.name}`}
                            value={attr.options?.some(option=>option.value===currentValue)?'':currentValue || ''}
                            onChange={e => onChange(attr.id, e.target.value)}
                            placeholder={`Enter custom ${attr.name.toLowerCase()}...`}
                            className="w-full px-3 py-2 bg-neutral-50 dark:bg-neutral-950 border border-indigo-300 dark:border-indigo-700 rounded-xl text-xs text-neutral-900 dark:text-white placeholder-neutral-400 focus:ring-1 focus:ring-indigo-500"
                          />
                        </div>
                      ) : (
                        /* Searchable Dropdown */
                        <SearchableSingleSelect
                          options={attr.options || []}
                          value={currentValue || ''}
                          placeholder={`Select ${attr.name}...`}
                          isOpen={openDropdownId === attr.id}
                          onToggle={() =>
                            setOpenDropdownId(openDropdownId === attr.id ? null : attr.id)
                          }
                          onClose={() => setOpenDropdownId(null)}
                          onSelect={val => {
                            onChange(attr.id, val);
                            setOpenDropdownId(null);
                          }}
                        />
                      )}
                    </>
                  )}

                  {/* 4. Multi-Select Field with Searchable Dropdown & Chips */}
                  {attr.inputType === 'multi_select' && (
                    <MultiSelectWithChips
                      options={attr.options || []}
                      selectedValues={Array.isArray(currentValue) ? currentValue : []}
                      allowCustom={!!attr.allowCustom}
                      placeholder={`Choose ${attr.name}...`}
                      isOpen={openDropdownId === attr.id}
                      onToggle={() =>
                        setOpenDropdownId(openDropdownId === attr.id ? null : attr.id)
                      }
                      onClose={() => setOpenDropdownId(null)}
                      onChange={newVals => onChange(attr.id, newVals)}
                    />
                  )}

                  {supplemental?.(attr.id)}
                  {/* Helper description text */}
                  {attr.description && (
                    <div className="text-[10px] text-neutral-400">
                      {attr.description}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
};

// ==========================================
// Custom Sub-Component: SearchableSingleSelect
// ==========================================
interface SearchableSingleSelectProps {
  options: AttributeOption[];
  value: string;
  placeholder?: string;
  isOpen: boolean;
  onToggle: () => void;
  onClose: () => void;
  onSelect: (val: string) => void;
}

const SearchableSingleSelect: React.FC<SearchableSingleSelectProps> = ({
  options,
  value,
  placeholder = 'Select option...',
  isOpen,
  onToggle,
  onClose,
  onSelect
}) => {
  const [search, setSearch] = useState('');
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Close on click outside
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        onClose();
      }
    };
    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [isOpen, onClose]);

  // Reset search when opened
  useEffect(() => {
    if (isOpen) setSearch('');
  }, [isOpen]);

  const filteredOptions = useMemo(() => {
    if (!search.trim()) return options;
    const q = search.toLowerCase();
    return options.filter(
      opt => opt.label.toLowerCase().includes(q) || opt.value.toLowerCase().includes(q)
    );
  }, [options, search]);

  const selectedOption = options.find(o => o.value === value);

  return (
    <div ref={dropdownRef} className="relative">
      {/* Trigger Button */}
      <button
        type="button"
        onClick={onToggle}
        className="w-full px-3 py-2 bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs flex items-center justify-between text-neutral-900 dark:text-white hover:border-neutral-400 dark:hover:border-neutral-600 transition-colors cursor-pointer"
      >
        <span className={`truncate ${!value ? 'text-neutral-400' : 'font-medium'}`}>
          {selectedOption ? selectedOption.label : value || placeholder}
        </span>
        <div className="flex items-center gap-1 shrink-0 ml-2">
          {value && (
            <span
              onClick={e => {
                e.stopPropagation();
                onSelect('');
              }}
              title="Clear selection"
              className="p-0.5 rounded-full hover:bg-neutral-200 dark:hover:bg-neutral-800 text-neutral-400 hover:text-neutral-700"
            >
              <X className="w-3 h-3" />
            </span>
          )}
          <ChevronDown className="w-3.5 h-3.5 text-neutral-400" />
        </div>
      </button>

      {/* Searchable Dropdown Menu */}
      {isOpen && (
        <div className="absolute top-full left-0 w-full mt-1.5 z-40 bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-100">
          {/* Search Box */}
          <div className="p-2 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-950/70 flex items-center gap-2">
            <Search className="w-3.5 h-3.5 text-neutral-400 shrink-0" />
            <input
              type="text"
              value={search}
              onChange={e => setSearch(e.target.value)}
              placeholder="Filter options..."
              autoFocus
              className="w-full bg-transparent text-xs text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-hidden"
            />
            {search && (
              <button
                type="button"
                onClick={() => setSearch('')}
                className="text-neutral-400 hover:text-neutral-600"
              >
                <X className="w-3 h-3" />
              </button>
            )}
          </div>

          {/* Options List */}
          <div className="max-h-48 overflow-y-auto divide-y divide-neutral-100 dark:divide-neutral-800/60">
            {filteredOptions.length === 0 ? (
              <div className="p-3 text-center text-xs text-neutral-400">
                No matching options found
              </div>
            ) : (
              filteredOptions.map(opt => {
                const isSelected = opt.value === value;
                return (
                  <button
                    key={opt.id}
                    type="button"
                    onClick={() => onSelect(opt.value)}
                    className={`w-full px-3 py-2 text-left text-xs flex items-center justify-between hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors cursor-pointer ${
                      isSelected
                        ? 'bg-indigo-50 dark:bg-indigo-950/60 font-semibold text-indigo-700 dark:text-indigo-300'
                        : 'text-neutral-800 dark:text-neutral-200'
                    }`}
                  >
                    <span className="truncate">{opt.label}</span>
                    {isSelected && <Check className="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400 shrink-0" />}
                  </button>
                );
              })
            )}
          </div>
        </div>
      )}
    </div>
  );
};

// ==========================================
// Custom Sub-Component: MultiSelectWithChips
// ==========================================
interface MultiSelectWithChipsProps {
  options: AttributeOption[];
  selectedValues: string[];
  allowCustom?: boolean;
  placeholder?: string;
  isOpen: boolean;
  onToggle: () => void;
  onClose: () => void;
  onChange: (newValues: string[]) => void;
}

const MultiSelectWithChips: React.FC<MultiSelectWithChipsProps> = ({
  options,
  selectedValues,
  allowCustom = false,
  placeholder = 'Select values...',
  isOpen,
  onToggle,
  onClose,
  onChange
}) => {
  const [search, setSearch] = useState('');
  const [customInput, setCustomInput] = useState('');
  const dropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        onClose();
      }
    };
    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [isOpen, onClose]);

  const filteredOptions = useMemo(() => {
    if (!search.trim()) return options;
    const q = search.toLowerCase();
    return options.filter(
      opt => opt.label.toLowerCase().includes(q) || opt.value.toLowerCase().includes(q)
    );
  }, [options, search]);

  const toggleValue = (val: string) => {
    if (selectedValues.includes(val)) {
      onChange(selectedValues.filter(v => v !== val));
    } else {
      onChange([...selectedValues, val]);
    }
  };

  const removeValue = (val: string) => {
    onChange(selectedValues.filter(v => v !== val));
  };

  const handleAddCustomValue = () => {
    const trimmed = customInput.trim();
    if (!trimmed) return;
    if (!selectedValues.includes(trimmed)) {
      onChange([...selectedValues, trimmed]);
    }
    setCustomInput('');
  };

  return (
    <div ref={dropdownRef} className="space-y-1.5 relative">
      {/* Trigger & Chips Container */}
      <div
        onClick={onToggle}
        className="w-full min-h-[38px] px-2.5 py-1.5 bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs flex flex-wrap items-center gap-1.5 hover:border-neutral-400 dark:hover:border-neutral-600 transition-colors cursor-pointer"
      >
        {selectedValues.length === 0 ? (
          <span className="text-neutral-400 px-1">{placeholder}</span>
        ) : (
          selectedValues.map(val => (
            <span
              key={val}
              className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-[11px] font-medium"
            >
              <span>{options.find(option=>option.value===val)?.label ?? val}</span>
              <button
                type="button"
                onClick={e => {
                  e.stopPropagation();
                  removeValue(val);
                }}
                className="hover:text-indigo-950 dark:hover:text-white p-0.5 rounded"
              >
                <X className="w-2.5 h-2.5" />
              </button>
            </span>
          ))
        )}

        <div className="ml-auto flex items-center gap-1 pl-1 text-neutral-400">
          <ChevronDown className="w-3.5 h-3.5" />
        </div>
      </div>

      {/* Checkbox Dropdown with Search & Optional Custom Input */}
      {isOpen && (
        <div className="absolute top-full left-0 w-full mt-1.5 z-40 bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-100">
          {/* Search Filter */}
          <div className="p-2 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-950/70 flex items-center gap-2">
            <Search className="w-3.5 h-3.5 text-neutral-400 shrink-0" />
            <input
              type="text"
              value={search}
              onChange={e => setSearch(e.target.value)}
              placeholder="Search or filter options..."
              autoFocus
              className="w-full bg-transparent text-xs text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-hidden"
            />
            {search && (
              <button
                type="button"
                onClick={() => setSearch('')}
                className="text-neutral-400 hover:text-neutral-600"
              >
                <X className="w-3 h-3" />
              </button>
            )}
          </div>

          {/* Checkboxes List */}
          <div className="max-h-48 overflow-y-auto divide-y divide-neutral-100 dark:divide-neutral-800/60 p-1">
            {filteredOptions.length === 0 ? (
              <div className="p-3 text-center text-xs text-neutral-400">
                No preset options found
              </div>
            ) : (
              filteredOptions.map(opt => {
                const isChecked = selectedValues.includes(opt.value);
                return (
                  <label
                    key={opt.id}
                    onClick={e => {
                      e.preventDefault();
                      toggleValue(opt.value);
                    }}
                    className={`px-2.5 py-1.5 rounded-lg flex items-center gap-2.5 cursor-pointer text-xs hover:bg-neutral-50 dark:hover:bg-neutral-800/80 transition-colors ${
                      isChecked ? 'font-semibold text-indigo-700 dark:text-indigo-300' : 'text-neutral-800 dark:text-neutral-200'
                    }`}
                  >
                    <input
                      type="checkbox"
                      checked={isChecked}
                      readOnly
                      className="rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <span className="truncate">{opt.label}</span>
                  </label>
                );
              })
            )}
          </div>

          {/* Custom value input entry (Only if platform allows custom for this field) */}
          {allowCustom && (
            <div className="p-2 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-950/50 flex items-center gap-1.5">
              <input
                type="text"
                value={customInput}
                onChange={e => setCustomInput(e.target.value)}
                onKeyDown={e => {
                  if (e.key === 'Enter') {
                    e.preventDefault();
                    handleAddCustomValue();
                  }
                }}
                placeholder="Add custom value..."
                className="flex-1 px-2.5 py-1.5 bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-lg text-xs text-neutral-900 dark:text-white placeholder-neutral-400 focus:ring-1 focus:ring-indigo-500"
              />
              <button
                type="button"
                disabled={!customInput.trim()}
                onClick={handleAddCustomValue}
                className="px-2.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold disabled:opacity-50 cursor-pointer flex items-center gap-1 shrink-0"
              >
                <Plus className="w-3 h-3" />
                Add
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

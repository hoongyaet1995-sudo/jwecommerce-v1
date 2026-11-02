import React, { useEffect, useState } from 'react';
import { useAdminCurrency } from '../../context/useAdminCurrency';
import { Link2, Package, Search, Trash2, X } from 'lucide-react';
import { CommerceAction } from '../commerce/CommerceUI';
import { readAdminBootstrap } from '../../integration/adminBootstrap';
import { useAdmin } from '../../context/AdminContext';
type Item = {
    id: string;
    title: string;
    image?: string;
    sku: string;
    price: number;
    status: string;
    categoryName?: string;
};
type Page = {
    data: Item[];
    total: number;
    page: number;
    lastPage: number;
};
const button = 'px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-xs font-semibold hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-40';
async function request(category: string, suffix = '', method = 'GET', body?: unknown) {
    const b = readAdminBootstrap();
    const response = await fetch(`${b.catalogUrl}/categories/${category}/products${suffix}`, { method, credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': b.csrfToken }, ...(body ? { body: JSON.stringify(body) } : {}) });
    const payload = response.status === 204 ? null : await response.json();
    if (!response.ok)
        throw new Error(payload?.errors ? Object.values(payload.errors).flat().join(' ') : payload?.message || 'Unable to update products.');
    return payload;
}
export function CategoryProducts({ id, name }: {
    id: string;
    name: string;
}) {
    const { money } = useAdminCurrency();
    const { refreshCatalog } = useAdmin();
    const [page, setPage] = useState(1), [result, setResult] = useState<Page | null>(null), [error, setError] = useState(''), [version, setVersion] = useState(0), [picker, setPicker] = useState(false), [busy, setBusy] = useState(false), [unlink, setUnlink] = useState<Item | null>(null);
    useEffect(() => { let active = true; setResult(null); setError(''); request(id, `?page=${page}`).then(r => { if (active) {
        if (page > r.lastPage)
            setPage(Math.max(1, r.lastPage));
        else
            setResult(r);
    } }).catch(e => { if (active)
        setError(e.message); }); return () => { active = false; }; }, [id, page, version]);
    const updated = async () => { setVersion(v => v + 1); await refreshCatalog(); };
    return <section className="rounded-3xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm">
 <div className="p-5 flex items-center justify-between"><div><h3 className="font-bold text-base">Products</h3><p className="text-xs text-neutral-500 mt-1">Products assigned directly to this category.</p></div><button className={button + ' bg-emerald-600 text-white flex gap-2 items-center'} onClick={() => setPicker(true)}><Link2 size={15}/>Link Product</button></div>
 {error && <p role="alert" className="p-5 text-red-500">{error}</p>}
 {!result && !error ? <p className="p-5 text-neutral-500">Loading products…</p> : result && <><div className="overflow-x-auto"><table className="w-full text-left text-xs"><thead className="bg-neutral-50 dark:bg-neutral-800/50 text-neutral-500 uppercase text-[10px]"><tr>{['Product', 'SKU', 'Price', 'Status', 'Actions'].map(h => <th key={h} className="p-4">{h}</th>)}</tr></thead><tbody>{result.data.map(p => <tr key={p.id} className="border-t border-neutral-100 dark:border-neutral-800"><td className="p-4"><div className="flex items-center gap-3">{p.image ? <img src={p.image} alt="" className="w-9 h-9 rounded-lg object-cover"/> : <Package size={22}/>}<a className="font-bold hover:text-indigo-500" href={`/admin/catalog/products?product=${p.id}`}>{p.title}</a></div></td><td className="p-4 max-w-48 break-words text-neutral-500">{p.sku || '—'}</td><td className="p-4">{money(p.price)}</td><td className="p-4 capitalize">{p.status}</td><td className="p-4"><CommerceAction label={p.title} items={[{ label: 'Edit Product', icon: <Package size={15}/>, onClick: () => { location.href = `/admin/catalog/products?product=${p.id}`; } }, { label: 'Unlink Product', danger: true, icon: <Trash2 size={15}/>, onClick: () => setUnlink(p) }]}/></td></tr>)}</tbody></table></div>{!result.total && <p className="p-8 text-center text-neutral-500">No products linked yet.</p>}<div className="p-4 border-t border-neutral-200 dark:border-neutral-800 flex items-center justify-between text-xs"><span className="text-neutral-500">{result.total ? `Showing ${(page - 1) * 20 + 1}–${Math.min(page * 20, result.total)} of ${result.total}` : '0 products'}</span><div className="flex items-center gap-3"><button className={button} disabled={page === 1} onClick={() => setPage(p => p - 1)}>Previous</button>{page} / {result.lastPage}<button className={button} disabled={page >= result.lastPage} onClick={() => setPage(p => p + 1)}>Next</button></div></div></>}
 {picker && <ProductPicker id={id} name={name} onClose={() => setPicker(false)} onSaved={async () => { setPicker(false); await updated(); }}/>}
 {unlink && <div className="fixed inset-0 z-[100] bg-black/50 flex items-center justify-center p-5"><div role="dialog" aria-modal="true" aria-label="Unlink product" className="p-6 rounded-2xl bg-white dark:bg-neutral-900 max-w-md w-full"><h3 className="font-bold">Unlink product?</h3><p className="my-4 text-sm text-neutral-500">Remove {unlink.title} from {name}. The product will remain in your catalogue.</p><div className="flex justify-end gap-2"><button className={button} disabled={busy} onClick={() => setUnlink(null)}>Cancel</button><button className={button + ' text-red-500'} disabled={busy} onClick={async () => { setBusy(true); try {
        await request(id, '/' + unlink.id, 'DELETE');
        setUnlink(null);
        await updated();
    }
    catch (e) {
        setError((e as Error).message);
    }
    finally {
        setBusy(false);
    } }}>Unlink Product</button></div></div></div>}
 </section>;
}
function ProductPicker({ id, name, onClose, onSaved }: {
    id: string;
    name: string;
    onClose: () => void;
    onSaved: () => Promise<void>;
}) {
    const [search, setSearch] = useState(''), [page, setPage] = useState(1), [result, setResult] = useState<Page | null>(null), [selected, setSelected] = useState<Set<string>>(new Set()), [busy, setBusy] = useState(false), [error, setError] = useState('');
    useEffect(() => { let active = true; setResult(null); const timer = setTimeout(() => { request(id, `?picker=1&page=${page}&search=${encodeURIComponent(search)}`).then(r => { if (active)
        setResult(r); }).catch(e => { if (active)
        setError(e.message); }); }, 200); return () => { active = false; clearTimeout(timer); }; }, [id, search, page]);
    return <div className="fixed inset-0 z-[100] bg-black/50 backdrop-blur-sm flex items-center justify-center p-5"><div role="dialog" aria-modal="true" aria-label="Link products" className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] flex flex-col"><div className="flex justify-between items-center"><h3 className="text-lg font-bold">Link products</h3><button aria-label="Close" className={button} disabled={busy} onClick={onClose}><X size={16}/></button></div><p className="text-xs text-neutral-500 mt-2">Add products to {name}. Products from another category will move here.</p><div className="relative my-4"><Search size={16} className="absolute left-3 top-3 text-neutral-500"/><input aria-label="Search products" placeholder="Search product name or SKU" value={search} onChange={e => { setSearch(e.target.value); setPage(1); }} className="pl-9 pr-3 py-3 text-xs w-full rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800"/></div>{error && <p role="alert" className="text-red-500 text-xs">{error}</p>}<div className="overflow-y-auto flex-1 min-h-0">{!result ? <p>Loading products…</p> : !result.total ? <p className="p-6 text-neutral-500 text-center">No products available.</p> : result.data.map(p => <label key={p.id} className="flex items-center gap-3 py-4 border-b border-neutral-200 dark:border-neutral-800 cursor-pointer"><input type="checkbox" role="switch" aria-label={p.title} checked={selected.has(p.id)} disabled={busy} onChange={() => setSelected(prev => { let next = new Set(prev); next.has(p.id) ? next.delete(p.id) : next.add(p.id); return next; })} className="appearance-none w-9 h-5 shrink-0 rounded-full bg-neutral-400 checked:bg-indigo-600 relative after:content-[''] after:absolute after:w-4 after:h-4 after:rounded-full after:bg-white after:left-0.5 after:top-0.5 checked:after:translate-x-4"/>{p.image && <img src={p.image} alt="" className="w-10 h-10 object-cover rounded-lg"/>}<div><strong className="text-xs">{p.title}</strong><p className="text-[11px] text-neutral-500 mt-1">{p.sku} · {p.categoryName || 'Unassigned'}</p></div></label>)}</div>{result && <div className="flex justify-between items-center text-xs mt-4"><button className={button} disabled={page === 1 || busy} onClick={() => setPage(p => p - 1)}>Previous</button><span>{page} / {result.lastPage}</span><button className={button} disabled={page >= result.lastPage || busy} onClick={() => setPage(p => p + 1)}>Next</button></div>}<div className="flex justify-between items-center pt-5 mt-4 border-t border-neutral-200 dark:border-neutral-800"><span className="text-xs text-neutral-500">{selected.size} selected</span><button disabled={!selected.size || busy} className={button + ' bg-indigo-600 text-white'} onClick={async () => { setBusy(true); setError(''); try {
        await request(id, '', 'POST', { productIds: [...selected] });
        await onSaved();
    }
    catch (e) {
        setError((e as Error).message);
    }
    finally {
        setBusy(false);
    } }}>{busy ? 'Linking…' : 'Link selected products'}</button></div></div></div>;
}

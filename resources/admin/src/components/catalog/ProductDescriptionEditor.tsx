import React, {useEffect, useState} from 'react';
import {EditorContent, useEditor} from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import {TableKit} from '@tiptap/extension-table';

export function ProductDescriptionEditor({value, onChange}: {value: string; onChange: (html:string)=>void}) {
  const [linkOpen,setLinkOpen]=useState(false);
  const [url,setUrl]=useState('');
  const [error,setError]=useState('');
  const editor=useEditor({
    extensions:[StarterKit.configure({link:{openOnClick:false},codeBlock:false,code:false}),Image,TableKit],
    content:value || '',
    shouldRerenderOnTransaction:true,
    editorProps:{attributes:{role:'textbox','aria-label':'Product description','aria-multiline':'true',class:'min-h-36 max-h-72 overflow-y-auto p-3 text-sm outline-none [&_p]:mb-2 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6 [&_a]:text-indigo-500 [&_a]:underline [&_h2]:text-lg [&_h2]:font-bold [&_blockquote]:border-l-2 [&_blockquote]:pl-3'}},
    onUpdate:({editor})=>onChange(editor.getHTML()),
  });
  useEffect(()=>{if(editor && value!==editor.getHTML()) editor.commands.setContent(value || '',{emitUpdate:false});},[value,editor]);
  if(!editor) return null;
  const actions=[
    {label:'Bold',active:editor.isActive('bold'),run:()=>editor.chain().focus().toggleBold().run()},
    {label:'Italic',active:editor.isActive('italic'),run:()=>editor.chain().focus().toggleItalic().run()},
    {label:'Bullet list',active:editor.isActive('bulletList'),run:()=>editor.chain().focus().toggleBulletList().run()},
    {label:'Numbered list',active:editor.isActive('orderedList'),run:()=>editor.chain().focus().toggleOrderedList().run()},
    {label:'Link',active:editor.isActive('link'),run:()=>{setUrl(editor.getAttributes('link').href || '');setError('');setLinkOpen(!linkOpen);}},
    {label:'Undo',disabled:!editor.can().undo(),run:()=>editor.chain().focus().undo().run()},
    {label:'Redo',disabled:!editor.can().redo(),run:()=>editor.chain().focus().redo().run()},
  ];
  return <div className="rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white overflow-hidden">
    <div role="toolbar" aria-label="Description formatting" className="flex flex-wrap gap-1 p-2 border-b border-neutral-200 dark:border-neutral-700">
      {actions.map(action=><button key={action.label} type="button" aria-label={action.label} aria-pressed={action.active} disabled={action.disabled} onMouseDown={event=>event.preventDefault()} onClick={action.run} className={`px-2 py-1 text-xs rounded-md disabled:opacity-40 ${action.active?'bg-indigo-600 text-white':'hover:bg-neutral-200 dark:hover:bg-neutral-700'}`}>{action.label}</button>)}
    </div>
    {linkOpen && <div className="flex flex-wrap gap-2 p-2 border-b border-neutral-200 dark:border-neutral-700">
      <input aria-label="Link URL" type="url" value={url} onChange={event=>setUrl(event.target.value)} placeholder="https://…" className="flex-1 min-w-0 p-2 text-xs rounded-md bg-white dark:bg-neutral-900"/>
      <button type="button" className="text-xs px-2 rounded-md bg-indigo-600 text-white" onClick={()=>{
        const href=url.trim();if(!/^(https?:\/\/|mailto:)/i.test(href)){setError('Enter an https://, http:// or mailto: link.');return;}
        editor.chain().focus().extendMarkRange('link').setLink({href}).run();setLinkOpen(false);
      }}>Apply link</button>
      <button type="button" className="text-xs px-2" onClick={()=>{editor.chain().focus().extendMarkRange('link').unsetLink().run();setLinkOpen(false);}}>Remove link</button>
      {error && <p role="alert" className="w-full text-xs text-red-500">{error}</p>}
    </div>}
    <EditorContent editor={editor}/>
  </div>;
}

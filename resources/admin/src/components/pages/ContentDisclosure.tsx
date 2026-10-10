import React from 'react';
export function ContentDisclosure({title,children,defaultOpen=true}:{title:string;children:React.ReactNode;defaultOpen?:boolean}) {
  const body=React.useRef<HTMLDivElement>(null);
  const [empty,setEmpty]=React.useState(false);
  React.useLayoutEffect(()=>{const element=body.current;if(element)setEmpty(!element.textContent?.trim()&&!element.querySelector('input,textarea,select,button,img,video'));},[children]);
  if(!React.Children.toArray(children).length)return null;
  return <details hidden={empty} open={defaultOpen} className="cms-settings-group cms-content-group"><summary>{title}</summary><div ref={body} className="cms-settings-group-content space-y-3">{children}</div></details>;
}

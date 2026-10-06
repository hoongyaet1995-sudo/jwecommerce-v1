export function imageValidation(file: File): string {
 return ['image/jpeg','image/png','image/webp'].includes(file.type) && file.size <= 5242880 ? '' : 'Choose a JPEG, PNG or WebP image up to 5 MB.';
}
export function imageFromClipboard(items: ArrayLike<DataTransferItem>): File | null {
 return Array.from(items).find(i=>['image/jpeg','image/png','image/webp'].includes(i.type))?.getAsFile() || null;
}

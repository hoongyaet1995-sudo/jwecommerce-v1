import contractManifest from '../../../cms/page-contract.v1.json';
import type { PageDocument } from '../types';

type JsonObject = Record<string, unknown>;
type ContractSchema = {
  $ref?: string;
  type?: string;
  types?: string[];
  required?: boolean;
  enum?: unknown[];
  minimum?: number;
  maximum?: number;
  format?: string;
  allowUnknownFields?: boolean;
  fields?: Record<string, ContractSchema>;
  items?: ContractSchema;
};

type ContractManifest = {
  version: number;
  document: ContractSchema;
  definitions: Record<string, ContractSchema>;
  section: {
    commonFields: string[];
    sectionTypes: Record<string, { fields: string[] }>;
  };
};

const manifest = contractManifest as ContractManifest;

const ROOT_OWNERSHIP_FIELDS = new Set([
  'id',
  'pageId',
  'status',
  'published',
  'publishedAt',
  'archivedAt',
  'createdAt',
  'updatedAt',
  'lockVersion',
  'contentHash',
  'hasPublishedSnapshot',
  'hasUnpublishedChanges',
  'document',
  'page',
]);

const MANAGED_MEDIA_PATTERN = /^\/storage\/cms\/media\/[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.(?:jpg|png|webp|gif|mp4|webm)$/;
const RESERVED_HOST_SUFFIXES = [
  '.localhost',
  '.local',
  '.internal',
  '.lan',
  '.home',
  '.corp',
  '.test',
  '.invalid',
  '.example',
];

export class DraftImportError extends Error {
  readonly code = 'cms_draft_import_invalid';

  constructor(readonly path: string, message: string) {
    super(`${path}: ${message}`);
    this.name = 'DraftImportError';
  }
}

const fail = (path: string, message: string): never => {
  throw new DraftImportError(path, message);
};

const isObject = (value: unknown): value is JsonObject => (
  typeof value === 'object' && value !== null && !Array.isArray(value)
);

const own = (value: object, key: string): boolean => Object.prototype.hasOwnProperty.call(value, key);

const pathFor = (base: string, key: string | number): string => (
  base === '' ? String(key) : `${base}.${key}`
);

const definition = (name: string): ContractSchema => {
  const schema = manifest.definitions[name];
  if (!schema) fail('$', `The canonical contract definition ${name} is unavailable.`);
  return schema;
};

const resolveSchema = (schema: ContractSchema): ContractSchema => {
  if (!schema.$ref) return schema;
  const { $ref, ...local } = schema;
  return { ...definition($ref), ...local };
};

const matchesType = (value: unknown, type: string): boolean => {
  switch (type) {
    case 'string': return typeof value === 'string';
    case 'number': return typeof value === 'number' && Number.isFinite(value);
    case 'integer': return typeof value === 'number' && Number.isInteger(value);
    case 'boolean': return typeof value === 'boolean';
    case 'null': return value === null;
    case 'array': return Array.isArray(value);
    case 'object': return isObject(value);
    default: return false;
  }
};

const containsEncodedControlOrBackslash = (url: string): boolean => {
  let candidate = url;

  for (let depth = 0; depth < 5; depth += 1) {
    if (/%(?:0[0-9a-f]|1[0-9a-f]|5c|7f)/i.test(candidate)) return true;

    let decoded: string;
    try {
      decoded = decodeURIComponent(candidate);
    } catch {
      return true;
    }

    if (decoded === candidate) return false;
    if (/[\u0000-\u001f\u007f\\]/.test(decoded)) return true;
    candidate = decoded;
  }

  return /%(?:0[0-9a-f]|1[0-9a-f]|5c|7f)/i.test(candidate);
};

const addressMatchesCidr = (
  address: readonly number[],
  network: readonly number[],
  prefixLength: number,
): boolean => {
  if (address.length !== network.length || prefixLength < 0 || prefixLength > address.length * 8) return false;
  const fullBytes = Math.floor(prefixLength / 8);
  for (let index = 0; index < fullBytes; index += 1) {
    if (address[index] !== network[index]) return false;
  }

  const remainingBits = prefixLength % 8;
  if (remainingBits === 0) return true;
  const mask = (0xff << (8 - remainingBits)) & 0xff;
  return (address[fullBytes] & mask) === (network[fullBytes] & mask);
};

const RESERVED_IPV4_CIDRS: ReadonlyArray<readonly [readonly number[], number]> = [
  [[0, 0, 0, 0], 8],
  [[10, 0, 0, 0], 8],
  [[100, 64, 0, 0], 10],
  [[127, 0, 0, 0], 8],
  [[169, 254, 0, 0], 16],
  [[172, 16, 0, 0], 12],
  [[192, 168, 0, 0], 16],
  [[224, 0, 0, 0], 3],
  [[192, 0, 0, 0], 24],
  [[192, 0, 2, 0], 24],
  [[198, 18, 0, 0], 15],
  [[198, 51, 100, 0], 24],
  [[203, 0, 113, 0], 24],
];

const isPublicIpv4 = (host: string): boolean => {
  const address = host.split('.').map(Number);
  if (address.length !== 4 || address.some(part => !Number.isInteger(part) || part < 0 || part > 255)) return false;
  return !RESERVED_IPV4_CIDRS.some(([network, prefix]) => addressMatchesCidr(address, network, prefix));
};

const parseIpv6Bytes = (host: string): number[] | null => {
  const halves = host.split('::');
  if (halves.length > 2) return null;

  const parseHalf = (half: string): number[] | null => {
    if (half === '') return [];
    const words = half.split(':');
    if (words.some(word => !/^[0-9a-f]{1,4}$/i.test(word))) return null;
    return words.map(word => Number.parseInt(word, 16));
  };

  const left = parseHalf(halves[0]);
  const right = parseHalf(halves[1] ?? '');
  if (!left || !right) return null;

  const missingWords = 8 - left.length - right.length;
  if ((halves.length === 1 && missingWords !== 0) || (halves.length === 2 && missingWords < 1)) return null;
  const words = halves.length === 1
    ? left
    : [...left, ...Array<number>(missingWords).fill(0), ...right];
  if (words.length !== 8) return null;

  return words.flatMap(word => [word >>> 8, word & 0xff]);
};

const RESERVED_IPV6_CIDRS: ReadonlyArray<readonly [readonly number[], number]> = [
  [Array<number>(16).fill(0), 128],
  [[0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1], 128],
  [[0xfc, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], 7],
  [[0xfe, 0x80, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], 10],
  [[0xff, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], 8],
  [[0x20, 0x01, 0x0d, 0xb8, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], 32],
];

const isCanonicalIpv4 = (host: string): boolean => {
  const parts = host.split('.');
  return parts.length === 4 && parts.every(part => (
    /^(?:0|[1-9][0-9]{0,2})$/.test(part) && Number(part) <= 255
  ));
};

const hasPhpCompatibleRawHost = (value: string): boolean => {
  const authority = value.match(/^https:\/\/([^/?#]*)/i)?.[1];
  if (authority === undefined) return true;
  if (/[^\u0000-\u007f]|%|@/.test(authority)) return false;

  const hostAndPort = authority;
  if (hostAndPort.startsWith('[')) return hostAndPort.includes(']');

  const portSeparator = hostAndPort.lastIndexOf(':');
  const rawHost = (portSeparator >= 0 ? hostAndPort.slice(0, portSeparator) : hostAndPort)
    .toLowerCase()
    .replace(/\.+$/, '');
  if (/^[0-9.]+$/.test(rawHost)) return isCanonicalIpv4(rawHost);
  if (/^(?:0x[0-9a-f]+)(?:\.(?:0x[0-9a-f]+|[0-9]+))*$/i.test(rawHost)) return false;

  const labels = rawHost.split('.');
  return rawHost.length <= 253 && labels.length > 1 && labels.every(label => (
    label.length <= 63 && /^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/i.test(label)
  ));
};

const isSafeMediaUrl = (value: string): boolean => {
  if (value === '') return true;
  if (MANAGED_MEDIA_PATTERN.test(value)) return true;
  if (value.startsWith('//') || /[\u0000-\u0020\u007f\\]/.test(value) || containsEncodedControlOrBackslash(value)) return false;
  if (!hasPhpCompatibleRawHost(value)) return false;

  let url: URL;
  try {
    url = new URL(value);
  } catch {
    return false;
  }

  if (url.protocol !== 'https:' || url.username !== '' || url.password !== '' || (url.port !== '' && url.port !== '443')) {
    return false;
  }

  const host = url.hostname.toLowerCase().replace(/\.+$/, '');
  if (host === '' || host === 'localhost') return false;
  if (/^[0-9.]+$/.test(host)) return isPublicIpv4(host);
  if (host.includes(':')) {
    const bare = host.replace(/^\[|\]$/g, '').toLowerCase();
    const mappedIpv4 = bare.match(/^::ffff:([0-9a-f]{1,4}):([0-9a-f]{1,4})$/);
    if (mappedIpv4) {
      const high = Number.parseInt(mappedIpv4[1], 16);
      const low = Number.parseInt(mappedIpv4[2], 16);
      return isPublicIpv4([
        high >>> 8,
        high & 0xff,
        low >>> 8,
        low & 0xff,
      ].join('.'));
    }
    const address = parseIpv6Bytes(bare);
    return address !== null
      && !RESERVED_IPV6_CIDRS.some(([network, prefix]) => addressMatchesCidr(address, network, prefix));
  }
  if (/^(?:0x[0-9a-f]+)(?:\.(?:0x[0-9a-f]+|[0-9]+))*$/i.test(host)) return false;
  if (!host.includes('.') || !/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/.test(host)) return false;
  if (['example.com', 'example.net', 'example.org'].includes(host)) return false;
  return !RESERVED_HOST_SUFFIXES.some(suffix => host.endsWith(suffix));
};

const validateObject = (value: JsonObject, schema: ContractSchema, path: string): void => {
  const fields = schema.fields ?? {};

  if (schema.allowUnknownFields === false) {
    for (const field of Object.keys(value)) {
      if (!own(fields, field)) fail(pathFor(path, field), 'This field is not supported by the page contract.');
    }
  }

  for (const [field, fieldSchema] of Object.entries(fields)) {
    const resolved = resolveSchema(fieldSchema);
    if (!own(value, field)) {
      if (resolved.required === true) fail(pathFor(path, field), 'This contract field is required.');
      continue;
    }
    validateValue(value[field], fieldSchema, pathFor(path, field));
  }
};

const validateSection = (value: unknown, path: string): void => {
  if (!isObject(value)) return fail(path, 'This value must be a JSON object.');
  const sectionType = value.type;
  if(sectionType==='promotion_highlights'&&Array.isArray(value.items)&&value.items.length>6)fail(pathFor(path,'items'),'Use up to six offer cards.');
  if (!own(value, 'type') || typeof sectionType !== 'string') {
    return fail(pathFor(path, 'type'), 'A supported section type is required.');
  }

  const sectionDefinition = manifest.section.sectionTypes[sectionType];
  if (!sectionDefinition) fail(pathFor(path, 'type'), 'This section type is not supported by the page contract.');

  const fields: Record<string, ContractSchema> = {};
  for (const field of manifest.section.commonFields) fields[field] = { ...definition(field), required: true };
  for (const field of sectionDefinition.fields) fields[field] = definition(field);

  validateObject(value, { type: 'object', allowUnknownFields: false, fields }, path);
};

function validateValue(value: unknown, unresolvedSchema: ContractSchema, path: string): void {
  if (unresolvedSchema.$ref === 'section') {
    validateSection(value, path);
    return;
  }

  const schema = resolveSchema(unresolvedSchema);
  const types = schema.types ?? (schema.type ? [schema.type] : []);
  if (!types.some(type => matchesType(value, type))) {
    fail(path, 'This value has an unsupported JSON type.');
  }

  if (typeof value === 'number' && (!Number.isFinite(value)
    || schema.minimum !== undefined && value < schema.minimum
    || schema.maximum !== undefined && value > schema.maximum)) {
    fail(path, 'This number is outside the allowed page contract range.');
  }

  if (schema.enum && !schema.enum.some(option => Object.is(option, value))) {
    fail(path, 'This value is not allowed by the page contract.');
  }

  if (schema.format === 'managed_or_external_url' && typeof value === 'string' && !isSafeMediaUrl(value)) {
    if (value.toLowerCase().startsWith('data:')) fail(path, 'Data URLs are not allowed in page documents.');
    fail(path, 'Media must use a managed asset URL or a safe public HTTPS URL.');
  }

  if (schema.type === 'array' && Array.isArray(value) && schema.items) {
    value.forEach((item, index) => validateValue(item, schema.items!, pathFor(path, index)));
  }

  if (schema.type === 'object' && isObject(value) && schema.fields) {
    validateObject(value, schema, path);
  }
}

const assertValidId = (value: unknown, path: string): string => {
  if (typeof value !== 'string' || !/^[A-Za-z0-9][A-Za-z0-9._:-]*$/.test(value)) {
    return fail(path, 'A non-empty stable ID is required.');
  }
  return value;
};

const assertUniqueIds = (document: JsonObject): void => {
  const sections = document.sections as JsonObject[];
  const sectionIds = new Set<string>();

  sections.forEach((section, sectionIndex) => {
    const sectionPath = `sections.${sectionIndex}`;
    const sectionId = assertValidId(section.id, `${sectionPath}.id`);
    if (sectionIds.has(sectionId)) fail(`${sectionPath}.id`, 'Duplicate section IDs are not allowed.');
    sectionIds.add(sectionId);

    const definitionForType = manifest.section.sectionTypes[section.type as string];
    for (const field of definitionForType.fields) {
      if (!own(section, field) || !Array.isArray(section[field])) continue;
      const fieldSchema = resolveSchema(definition(field));
      if (fieldSchema.type !== 'array' || !fieldSchema.items) continue;
      const itemSchema = resolveSchema(fieldSchema.items);
      if (itemSchema.type !== 'object' || !itemSchema.fields || !own(itemSchema.fields, 'id')) continue;

      const itemIds = new Set<string>();
      (section[field] as JsonObject[]).forEach((item, itemIndex) => {
        const idPath = `${sectionPath}.${field}.${itemIndex}.id`;
        const itemId = assertValidId(item.id, idPath);
        if (itemIds.has(itemId)) fail(idPath, 'Duplicate repeatable-item IDs are not allowed.');
        itemIds.add(itemId);
      });
    }
  });
};

export function parseDraftImport(raw: string, currentPageId: string): PageDocument {
  if (currentPageId.trim() === '') fail('currentPageId', 'A trusted current page ID is required.');

  let parsed: unknown;
  try {
    parsed = JSON.parse(raw);
  } catch {
    fail('$', 'Malformed JSON.');
  }

  if (!isObject(parsed)) return fail('$', 'The imported draft must be one canonical JSON object.');

  if (own(parsed, 'schemaVersion')) {
    if (parsed.schemaVersion !== manifest.version) fail('schemaVersion', 'Unsupported schema version.');
    fail('schemaVersion', 'Version keys are not part of the canonical draft document body.');
  }

  for (const field of ROOT_OWNERSHIP_FIELDS) {
    if (own(parsed, field)) fail(field, 'Page identity, lifecycle, and document envelopes cannot be imported.');
  }

  validateValue(parsed, manifest.document, '');
  assertUniqueIds(parsed);

  return structuredClone(parsed) as unknown as PageDocument;
}

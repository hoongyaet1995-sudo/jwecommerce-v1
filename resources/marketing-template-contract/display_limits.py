"""Conservative display budgets for the unchanged v1 composition, not narration.

Character limits guide generation; Chromium remains the final glyph/line-fit gate.
No text truncation, font overrides, or product-specific rules are permitted.
"""
import copy

LIMITS = {
    'en': {'hook': 24, 'benefit': 24, 'support': 38, 'cta': 12, 'detail_title': 18, 'detail_copy': 38},
    'zh': {'hook': 12, 'benefit': 12, 'support': 24, 'cta': 10, 'detail_title': 8, 'detail_copy': 20},
}

def bounded_schema(schema, language):
    result = copy.deepcopy(schema)
    draft = result['properties']['draft']['anyOf'][0]['properties']
    limits = LIMITS[language]
    for key in ('hook', 'benefit', 'support', 'cta'):
        draft['copy']['properties'][key] = {'type': 'string', 'maxLength': limits[key]}
    for key in ('title', 'copy'):
        draft['details']['items']['properties'][key] = {'type': 'string', 'maxLength': limits['detail_' + key]}
    draft['narration']['properties']['text'] = {'type': 'string', 'maxLength': 260 if language == 'en' else 80}
    return result

def display_errors(draft, language):
    limits = LIMITS[language]
    fields = [('copy.' + key, value, limits[key]) for key, value in draft['copy'].items()]
    fields += [(f'details.{i}.{key}', d[key], limits['detail_' + key]) for i, d in enumerate(draft['details']) for key in ('title', 'copy')]
    return ['display_copy_limit:' + field for field, text, limit in fields if len(text) > limit or '\n' in text or '\r' in text]

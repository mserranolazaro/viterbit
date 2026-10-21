"""
Builds assets/fonts/material-symbols-rounded-subset.woff2 with only the icons the UI uses.

The full Material Symbols Rounded font is about 5.4 MB; the subset is about 33 KB.
Add an icon name to ICONS and run (needs `pip install fonttools brotli`):

    python resources/fonts/subset_material_symbols.py path/to/material-symbols-rounded.woff2
"""
import sys
from pathlib import Path

from fontTools import subset
from fontTools.ttLib import TTFont

ICONS = [
    'add', 'arrow_back', 'arrow_forward', 'auto_awesome', 'call', 'check_circle', 'chevron_left',
    'chevron_right', 'close', 'description', 'error', 'event', 'expand_more', 'groups', 'history',
    'inbox', 'info', 'mail', 'notes', 'person_search', 'place', 'progress_activity', 'schedule',
    'search', 'send', 'sync', 'task_alt', 'tune', 'update', 'work',
]

OUTPUT = Path(__file__).resolve().parents[2] / 'assets/fonts/material-symbols-rounded-subset.woff2'

font = TTFont(sys.argv[1])
names = set(font.getGlyphOrder())
glyphs = [glyph for icon in ICONS for glyph in (icon, icon + '.fill') if glyph in names]

options = subset.Options()
options.flavor = 'woff2'
options.layout_features = ['liga', 'rlig', 'rvrn']  # ligatures + the FILL axis variants
options.layout_closure = False
options.name_IDs = ['*']

subsetter = subset.Subsetter(options)
subsetter.populate(glyphs=glyphs, text='abcdefghijklmnopqrstuvwxyz_0123456789')
subsetter.subset(font)
font.save(OUTPUT)
print(f'{len(glyphs)} glyphs written to {OUTPUT}')

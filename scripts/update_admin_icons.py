import glob
from pathlib import Path
replacements = {
    '💊': '📦',
    '✅': '📋',
    '🧾': '🧑‍🤝‍🧑'
}
updated = []
for path in glob.glob('admin/*.php'):
    p = Path(path)
    text = p.read_text(encoding='utf-8')
    new = text
    for old, newchar in replacements.items():
        new = new.replace(old, newchar)
    if new != text:
        p.write_text(new, encoding='utf-8')
        updated.append(path)
print('updated', updated)

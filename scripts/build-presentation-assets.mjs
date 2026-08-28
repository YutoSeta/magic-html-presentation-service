import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const heroiconsRoot = resolve(projectRoot, 'node_modules/heroicons');
const outputRoot = resolve(projectRoot, 'resources/presentation');
const iconNames = {
    'primary-action': 'arrow-right',
    success: 'check',
    feature: 'sparkles',
    search: 'magnifying-glass',
    email: 'envelope',
    phone: 'phone',
    play: 'play',
    disclosure: 'chevron-down',
};

await mkdir(outputRoot, { recursive: true });

const icons = {};
const digest = createHash('sha256');

for (const intent of Object.keys(iconNames).sort()) {
    const name = iconNames[intent];
    icons[intent] = { name };

    for (const style of ['outline', 'solid']) {
        const sourcePath = resolve(heroiconsRoot, '24', style, `${name}.svg`);
        const source = await readFile(sourcePath, 'utf8');
        const opening = source.match(/^<svg\s+([^>]+)>/u);
        const body = source.match(/^<svg\s+[^>]+>\s*([\s\S]*?)\s*<\/svg>\s*$/u);

        if (!opening || !body) {
            throw new Error(`Unable to parse Heroicon ${style}/${name}.`);
        }

        const viewBox = opening[1].match(/viewBox="([^"]+)"/u)?.[1];
        const fill = opening[1].match(/fill="([^"]+)"/u)?.[1];
        const strokeWidth = opening[1].match(/stroke-width="([^"]+)"/u)?.[1] ?? null;

        if (!viewBox || !fill) {
            throw new Error(`Heroicon ${style}/${name} is missing required SVG attributes.`);
        }

        icons[intent][style] = {
            view_box: viewBox,
            fill,
            stroke_width: strokeWidth,
            body: body[1].trim(),
        };
        digest.update(`${intent}\0${style}\0${source}\0`);
    }
}

const catalog = {
    provider: 'heroicons-v2',
    version: '2.2.0',
    license: 'MIT',
    source_digest: digest.digest('hex'),
    icons,
};

await writeFile(
    resolve(outputRoot, 'heroicons.json'),
    `${JSON.stringify(catalog, null, 2)}\n`,
    'utf8',
);
await writeFile(
    resolve(outputRoot, 'HEROICONS_LICENSE'),
    await readFile(resolve(heroiconsRoot, 'LICENSE'), 'utf8'),
    'utf8',
);

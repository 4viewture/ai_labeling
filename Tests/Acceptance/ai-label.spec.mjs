import { readFileSync, existsSync } from 'node:fs';
import { test, expect } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';

const file = new URL('./ai-label.scenarios.json', import.meta.url);
const scenarios = existsSync(file) ? JSON.parse(readFileSync(file, 'utf8')) : {};
const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

async function open(page, scenario) {
  await page.goto(scenario.path, { waitUntil: 'networkidle' });
  const element = page.locator(scenario.element).first();
  await element.evaluate((el) => el.closest('details')?.setAttribute('open', ''));
  await element.scrollIntoViewIfNeeded();
  return element;
}

async function commonChecks(page, element, selector) {
  const label = element.locator('.ai-label').first();
  await expect(label).toBeVisible();
  await expect(label.locator('.ai-label__text')).not.toHaveText('');
  await expect(label.locator('svg.ai-label__icon[aria-hidden="true"][focusable="false"]')).toHaveCount(1);
  await expect(label).not.toHaveAttribute('title', /.*/);
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
  expect(overflow, 'no horizontal scrolling').toBe(false);
  const box = await label.boundingBox();
  expect(box.x + box.width).toBeLessThanOrEqual((page.viewportSize()?.width ?? 1280) + 1);
  const axe = await new AxeBuilder({ page }).include(selector).withTags(TAGS).analyze();
  expect(axe.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target).join(', ')}`)).toEqual([]);
}

test.describe('AI label', () => {
  test.skip(Object.keys(scenarios).length === 0, 'copy ai-label.scenarios.example.json to ai-label.scenarios.json');

  test('text CE with header: label after heading, heading name clean', async ({ page }) => {
    const s = scenarios['text-header'];
    test.skip(!s, 'scenario "text-header" not configured');
    const element = await open(page, s);
    await expect(element).toHaveAttribute('data-ai-label', /generated|modified/);
    const heading = element.locator('header :is(h1,h2,h3,h4,h5,h6)').first();
    const text = await element.locator('.ai-label__text').first().innerText();
    expect(await heading.innerText()).not.toContain(text);
    expect(await element.locator('header > .ai-label-line').count()).toBe(1);
    await commonChecks(page, element, s.element);
  });

  test('CE with frame_class none: anchor untouched, no new siblings', async ({ page }) => {
    const s = scenarios['frame-none'];
    test.skip(!s, 'scenario "frame-none" not configured');
    await open(page, s);
    const header = page.locator(`${s.element} + header, ${s.element} ~ header`).first();
    await expect(header).toHaveAttribute('data-ai-label', /generated|modified/);
    await commonChecks(page, header, `${s.element} ~ header`);
  });

  test('image: alt text, figcaption and burned-in symbol', async ({ page }) => {
    const s = scenarios['image'];
    test.skip(!s, 'scenario "image" not configured');
    const element = await open(page, s);
    const img = element.locator('img').first();
    await img.evaluate((i) => (i.complete ? null : new Promise((r) => i.addEventListener('load', r, { once: true }))));
    expect(await img.getAttribute('alt')).toMatch(/\(.+\)$/);
    await expect(element.locator('figcaption .ai-label')).toHaveCount(1);
    const contrast = await img.evaluate((i) => {
      const c = document.createElement('canvas');
      c.width = i.naturalWidth; c.height = i.naturalHeight;
      const ctx = c.getContext('2d'); ctx.drawImage(i, 0, 0);
      const shorter = Math.min(c.width, c.height);
      const size = Math.max(24, Math.min(96, Math.round(shorter * 0.08)));
      const margin = Math.max(4, Math.round(shorter * 0.02));
      const lum = (x, y) => { const [r, g, b] = ctx.getImageData(x, y, 1, 1).data; const f = (v) => { v /= 255; return v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
      const disc = lum(c.width - margin - size / 2, margin + 3);
      const outside = lum(c.width - margin - size - 4, margin + size / 2);
      return (Math.max(disc, outside) + 0.05) / (Math.min(disc, outside) + 0.05);
    });
    expect(contrast, 'symbol disc vs. image').toBeGreaterThanOrEqual(3);
    await commonChecks(page, element, s.element);
  });

  test('video: overlay at the top, never over the controls, clicks pass through', async ({ page }) => {
    const s = scenarios['video'];
    test.skip(!s, 'scenario "video" not configured');
    const element = await open(page, s);
    const play = element.locator('.video-shariff-play');
    if (await play.count()) await play.click();
    const player = element.locator('iframe, video').first();
    await player.waitFor();
    const label = element.locator('.ai-label--overlay').first();
    const [p, l] = [await player.boundingBox(), await label.boundingBox()];
    const anchored = await page.evaluate(() => CSS.supports('anchor-name: --x') && CSS.supports('anchor-scope: --x'));
    if (anchored) {
      expect(l.y).toBeGreaterThanOrEqual(p.y);
      expect(l.y + l.height).toBeLessThan(p.y + p.height * 2 / 3);
      expect(l.x + l.width).toBeLessThanOrEqual(p.x + p.width + 1);
      // pointer-events: none -> the label is never the hit target (site overlays such as cookie or
      // language banners may legitimately cover the point, so only the label itself is excluded)
      const hitsLabel = await page.evaluate(([x, y]) => !!document.elementFromPoint(x, y)?.closest('.ai-label'), [l.x + l.width / 2, l.y + l.height / 2]);
      expect(hitsLabel).toBe(false);
    } else {
      expect(l.y).toBeGreaterThanOrEqual(p.y + p.height - 1);
    }
    await expect(label).toHaveCSS('pointer-events', 'none');
  });

  test('news detail: label after the headline', async ({ page }) => {
    const s = scenarios['news-detail'];
    test.skip(!s, 'scenario "news-detail" not configured');
    const element = await open(page, s);
    await expect(element.locator('h1 + .ai-label, h1 ~ .ai-label').first()).toHaveAttribute('data-ai-label', 'modified');
    await commonChecks(page, element, s.element);
  });

  test('text spacing (WCAG 1.4.12) does not clip the label', async ({ page }) => {
    const s = scenarios['text-header'];
    test.skip(!s, 'scenario "text-header" not configured');
    const element = await open(page, s);
    await page.addStyleTag({ content: '* { line-height: 1.5 !important; letter-spacing: .12em !important; word-spacing: .16em !important; } p { margin-bottom: 2em !important; }' });
    const clipped = await element.locator('.ai-label').first().evaluate((l) => l.scrollWidth > l.clientWidth + 1 || l.scrollHeight > l.clientHeight + 1);
    expect(clipped).toBe(false);
  });
});

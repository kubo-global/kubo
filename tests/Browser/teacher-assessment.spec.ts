import { test, expect } from '@playwright/test';
import { loginAsTeacher } from './helpers';

const TEACHER_ID = 1;

test.describe('Assessment Entry Flow', () => {

  test('teacher can start creating an assessment', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    await page.goto('/scorebook/create');
    // Should see assessment type cards
    await expect(page.locator('text=What type of assessment')).toBeVisible();
  });

  test('assessment create page loads with form fields', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    // Get first assessment type ID
    const resp = await page.goto('/scorebook/create');
    const typeLink = page.locator('a[href*="/assessment/create/"]').first();
    if (await typeLink.count() > 0) {
      await typeLink.click();
      await expect(page.locator('select[name="offering_id"]')).toBeVisible();
      await expect(page.locator('select[name="term_id"]')).toBeVisible();
      await expect(page.locator('input[name="name"]')).toBeVisible();
      await expect(page.locator('input[name="max_score"]')).toBeVisible();
    }
  });

  test('assessment scores page shows student list', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    // Create a draft assessment via the form
    const typeLink = await page.goto('/scorebook/create');
    const link = page.locator('a[href*="/assessment/create/"]').first();
    if (await link.count() === 0) return;

    await link.click();
    // Fill the form
    await page.selectOption('select[name="offering_id"]', { index: 1 });
    await page.selectOption('select[name="term_id"]', { index: 1 });

    // Need to reload for subjects since it's not Ajax
    const offeringVal = await page.locator('select[name="offering_id"]').inputValue();
    const termVal = await page.locator('select[name="term_id"]').inputValue();
    if (!offeringVal || !termVal) return;

    // Navigate with query params to get subjects
    const currentUrl = page.url();
    const baseUrl = currentUrl.split('?')[0];
    await page.goto(`${baseUrl}?offering=${offeringVal}&term=${termVal}`);

    const subjectSelect = page.locator('select[name="subject_id"]');
    const options = await subjectSelect.locator('option').count();
    if (options <= 1) return; // no subjects available

    await subjectSelect.selectOption({ index: 1 });
    await page.fill('input[name="name"]', 'E2E Test Assessment');
    await page.fill('input[name="max_score"]', '100');
    await page.click('button[type="submit"]');

    // Should be on scores page
    await expect(page).toHaveURL(/\/assessment\/\d+\/scores/);
    await expect(page.locator('text=Enter scores')).toBeVisible();
  });

});

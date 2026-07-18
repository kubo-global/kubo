import { test, expect } from '@playwright/test';
import { loginAsTeacher } from './helpers';

const TEACHER_ID = 1;

test.describe('Reports & Progress', () => {

  test('progress index shows teacher classes', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    await page.goto('/progress');
    const classes = page.locator('a[href*="/progress/"]');
    const empty = page.locator('text=No classes found');
    await expect(classes.first().or(empty)).toBeVisible();
  });

  test('progress detail loads for a class', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    await page.goto('/progress');
    const classLink = page.locator('a[href*="/progress/"]').first();
    if (await classLink.count() > 0) {
      await classLink.click();
      await expect(page).toHaveURL(/\/progress\/\d+/);
      // Page should show either student data or "No students" message
      const content = page.locator('text=Exercises this week');
      const empty = page.locator('text=No students enrolled');
      await expect(content.or(empty)).toBeVisible();
    }
  });

  test('term report overview loads', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    await page.goto('/termreports/overview');
    await expect(page).toHaveURL(/\/termreports\/overview/);
  });

  test('scorebook page loads', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    await page.goto('/reporting/grades');
    await expect(page).toHaveURL(/\/reporting\/grades/);
  });

});

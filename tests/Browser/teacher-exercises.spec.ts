import { test, expect } from '@playwright/test';
import { loginAsTeacher } from './helpers';

const TEACHER_ID = 1;

test.describe('Exercise Management', () => {

  test('teacher can view exercises list', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    await page.goto('/exercises');
    await expect(page.getByRole('link', { name: 'Create Exercise' })).toBeVisible();
  });

  test('exercise creator shows skill selection', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    await page.goto('/exercises/create');
    await expect(page.locator('text=What skill is this exercise for')).toBeVisible();
  });

  test('content mapping page loads', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    await page.goto('/content');
    await expect(page).toHaveURL(/\/content/);
    await expect(page.getByRole('heading', { name: /video mapping/i })).toBeVisible();
  });

});

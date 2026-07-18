import { test, expect } from '@playwright/test';
import { loginAsTeacher } from './helpers';

// Teacher ID 1 = Annette (password: secret)
const TEACHER_ID = 1;

test.describe('Teacher Flow', () => {

  test('teacher can log in and sees dashboard', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
  });

  test('teacher can access progress dashboard', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    await page.goto('/progress');
    const classes = page.locator('a[href*="/progress/"]');
    const empty = page.locator('text=No classes found');
    await expect(classes.first().or(empty)).toBeVisible();
  });

  test('teacher can access progress detail for a class', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    await page.goto('/progress');
    const classLink = page.locator('a[href*="/progress/"]').first();
    if (await classLink.count() > 0) {
      await classLink.click();
      await expect(page).toHaveURL(/\/progress\/\d+/);
      const content = page.locator('text=Exercises this week');
      const empty = page.locator('text=No students enrolled');
      await expect(content.or(empty)).toBeVisible();
    }
  });

  test('admin can access the content mapper', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    await page.goto('/content');
    await expect(page.getByRole('heading', { name: /video mapping/i })).toBeVisible();
  });

  test('teacher can access exercise creator', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    await page.goto('/exercises/create');
    await expect(page.locator('text=What skill is this exercise for')).toBeVisible();
  });

  test('teacher can view exercises list', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    await page.goto('/exercises');
    await expect(page.getByRole('link', { name: 'Create Exercise' })).toBeVisible();
  });

  test('teacher can access all key routes', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    // Verify all teacher routes return 200
    for (const path of ['/students', '/progress', '/exercises', '/content', '/learn']) {
      const resp = await page.goto(path);
      expect(resp?.status(), `${path} should return 200`).toBe(200);
    }
  });

});

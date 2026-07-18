import { test, expect } from '@playwright/test';
import { loginAsStudent, loginAsTeacher } from './helpers';

const STUDENT_ID = 268;
const TEACHER_ID = 1;

test.describe('Access Control', () => {

  test('unauthenticated user is redirected to login', async ({ page }) => {
    await page.goto('/learn');
    await expect(page).toHaveURL(/\/login/);
  });

  test('student cannot access teacher pages', async ({ page }) => {
    await loginAsStudent(page, STUDENT_ID);

    const progressResp = await page.goto('/progress');
    expect(progressResp?.status()).toBe(403);

    const exerciseResp = await page.goto('/exercises');
    expect(exerciseResp?.status()).toBe(403);

    const contentResp = await page.goto('/content');
    expect(contentResp?.status()).toBe(403);
  });

  test('teacher can access learn page', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    await page.goto('/learn');
    await expect(page).toHaveURL(/\/learn/);
  });

});

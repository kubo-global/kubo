import { test, expect } from '@playwright/test';
import { loginAsTeacher } from './helpers';

// Teacher ID 1 = Annette (has headmaster or admin role)
const ADMIN_ID = 1;

test.describe('School Year Rollover', () => {

  test('rollover page loads for admin', async ({ page }) => {
    await loginAsTeacher(page, ADMIN_ID);
    await page.goto('/rollover');
    await expect(page.locator('text=Create a new school year')).toBeVisible();
  });

  test('rollover form pre-fills from previous year', async ({ page }) => {
    await loginAsTeacher(page, ADMIN_ID);
    await page.goto('/rollover');
    // Name field should be pre-filled with next year
    const nameInput = page.locator('input[name="name"]');
    const value = await nameInput.inputValue();
    expect(value).toBeTruthy();
  });

  test('student cannot access rollover', async ({ page }) => {
    // Use student login
    await page.goto('/student-login/go/268');
    await page.waitForURL(/\/learn/);
    const resp = await page.goto('/rollover');
    expect(resp?.status()).toBe(403);
  });

});

import { Page } from '@playwright/test';

/**
 * Log in as a teacher via the standard login form.
 * The form uses a select dropdown (name="id") + password field.
 */
export async function loginAsTeacher(page: Page, userId: number, password = 'secret') {
  await page.goto('/login');
  await page.selectOption('select[name="id"]', String(userId));
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/(home|dashboard|students)/, { timeout: 10000 });
}

/**
 * Log in as a student via the name-based student login.
 * Flow: /student-login → select class → select student name → auto-login.
 */
export async function loginAsStudent(page: Page, studentId: number) {
  // Direct login URL — no class selection needed
  await page.goto(`/student-login/go/${studentId}`);
  await page.waitForURL(/\/learn|\/home|\/dashboard/, { timeout: 10000 });
}

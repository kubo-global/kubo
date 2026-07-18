import { test, expect } from '@playwright/test';
import { loginAsStudent } from './helpers';

// Student ID 203 = Ayebatari Kimberly (has skill data)
const STUDENT_ID = 268;

test.describe('Student Learn Flow', () => {

  test('student can log in and sees learn page', async ({ page }) => {
    await loginAsStudent(page, STUDENT_ID);
    await expect(page).toHaveURL(/\/learn/);
  });

  test('student sees homework or practice', async ({ page }) => {
    await loginAsStudent(page, STUDENT_ID);
    await page.goto('/learn');
    const homework = page.locator('text=Homework');
    const practice = page.locator('text=Practice');
    const allDone = page.locator('text=All done');
    const noExercises = page.locator('text=No exercises available');
    await expect(homework.or(practice).or(allDone).or(noExercises).first()).toBeVisible();
  });

  test('student can browse skills', async ({ page }) => {
    await loginAsStudent(page, STUDENT_ID);
    await page.goto('/learn');

    const browse = page.locator('summary:has-text("Browse all skills")');
    if (await browse.count() > 0) {
      await browse.click();
      const skillLinks = page.locator('details >> a[href*="/learn/skill/"]');
      await expect(skillLinks.first()).toBeVisible();
    }
  });

  test('student sees skill page with practice button', async ({ page }) => {
    await loginAsStudent(page, STUDENT_ID);
    await page.goto('/learn');

    const browse = page.locator('summary:has-text("Browse all skills")');
    if (await browse.count() > 0) {
      await browse.click();
      const skillLink = page.locator('details >> a[href*="/learn/skill/"]').first();
      if (await skillLink.count() > 0) {
        await skillLink.click();
        await expect(page).toHaveURL(/\/learn\/skill\/\d+/);
        const practiceBtn = page.locator('button:has-text("Start practice")');
        const noExercise = page.locator('text=No exercise available');
        await expect(practiceBtn.or(noExercise).first()).toBeVisible();
      }
    }
  });

  test('student does not see teacher pages in nav', async ({ page }) => {
    await loginAsStudent(page, STUDENT_ID);
    await page.goto('/learn');
    // Students should not have links to teacher-only pages
    await expect(page.locator('a[href*="/progress"]')).not.toBeVisible();
    await expect(page.locator('a[href*="/exercises"]')).not.toBeVisible();
  });

});

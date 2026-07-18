import { test, expect } from '@playwright/test';
import { loginAsTeacher } from './helpers';

const TEACHER_ID = 1;

test.describe('Student Management', () => {

  test('my students page loads', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    await page.goto('/students');
    await expect(page).toHaveURL(/\/students/);
  });

  test('lesson plan page loads', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    await page.goto('/lessons');
    await expect(page).toHaveURL(/\/lessons/);
  });

  // Regression: Livewire 3 serializes Eloquent models as references with no
  // attribute data, so wire:model="user.first_name" hydrated as null on the
  // client and wiped the input. The component now binds to scalar properties
  // — this test asserts they're populated when entering edit mode.
  test('edit profile populates first/last name inputs from the student', async ({ page }) => {
    await loginAsTeacher(page, TEACHER_ID);
    await page.goto('/students');

    const firstStudentLink = page.locator('a[href*="/students/"]').first();
    await firstStudentLink.click();
    await page.waitForURL(/\/students\/\d+/);

    await page.getByRole('button', { name: /edit profile/i }).click();

    const firstName = page.locator('#fname');
    const lastName = page.locator('#lname');
    await expect(firstName).toBeVisible();
    await expect(lastName).toBeVisible();
    await expect(firstName).not.toHaveValue('');
    await expect(lastName).not.toHaveValue('');
  });

});

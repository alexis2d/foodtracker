export type FoodSource = 'custom' | 'off' | 'seed';
export type FoodUnit = 'g' | 'ml' | 'unit';
export type MealType = 'breakfast' | 'lunch' | 'dinner' | 'snack';

export const MEAL_TYPES: MealType[] = ['breakfast', 'lunch', 'dinner', 'snack'];

export const MEAL_LABELS: Record<MealType, string> = {
  breakfast: 'Petit-déjeuner',
  lunch: 'Déjeuner',
  dinner: 'Dîner',
  snack: 'Collation',
};

export interface Food {
  id: number | null;
  source: FoodSource;
  name: string;
  barcode: string | null;
  offId: string | null;
  kcalPer100: number;
  proteinPer100: number;
  carbsPer100: number;
  fatPer100: number;
  fiberPer100: number | null;
  defaultUnit: FoodUnit;
  unitWeightGrams: number | null;
  editable: boolean;
}

export interface DiaryEntry {
  id: number;
  food: Food;
  quantity: number;
  unit: FoodUnit;
  mealType: MealType;
  consumedAt: string;
  kcal: number;
  protein: number;
  carbs: number;
  fat: number;
}

export interface DailySummary {
  date: string;
  totals: { kcal: number; protein: number; carbs: number; fat: number };
  goal: { kcal: number };
  remaining: { kcal: number };
  entriesByMeal: Record<MealType, DiaryEntry[]>;
}

export interface CurrentUser {
  id: number;
  email: string;
  dailyCalorieGoal: number;
}

import { useState, useEffect, type FormEvent } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { api, ApiError } from '../lib/api';
import { IngredientPickerModal } from '../components/IngredientPickerModal';
import type { Food, FoodUnit, Meal } from '../lib/types';
import styles from './MealFormPage.module.css';

interface Row {
  food: Food;
  quantity: string;
  unit: FoodUnit;
}

function gramsFor(food: Food, quantity: number, unit: FoodUnit): number {
  if (unit === 'unit') {
    return quantity * (food.unitWeightGrams ?? 100);
  }
  return quantity;
}

export function MealFormPage() {
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const isEditing = id !== undefined;

  const mealQuery = useQuery({
    queryKey: ['meal', id],
    queryFn: () => api.get<Meal>(`/api/meals/${id}`),
    enabled: isEditing,
  });

  const [name, setName] = useState('');
  const [rows, setRows] = useState<Row[]>([]);
  const [pickerOpen, setPickerOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (mealQuery.data) {
      setName(mealQuery.data.name);
      setRows(
        mealQuery.data.ingredients.map((ingredient) => ({
          food: ingredient.food,
          quantity: String(ingredient.quantity),
          unit: ingredient.unit,
        })),
      );
    }
  }, [mealQuery.data]);

  function addRow(food: Food, quantity: number, unit: FoodUnit) {
    setRows((prev) => [...prev, { food, quantity: String(quantity), unit }]);
  }

  function removeRow(index: number) {
    setRows((prev) => prev.filter((_, i) => i !== index));
  }

  function updateQuantity(index: number, quantity: string) {
    setRows((prev) => prev.map((row, i) => (i === index ? { ...row, quantity } : row)));
  }

  let totalGrams = 0;
  let totalKcal = 0;
  let totalProtein = 0;
  let totalCarbs = 0;
  let totalFat = 0;

  const rowContributions = rows.map((row) => {
    const quantity = Number(row.quantity) || 0;
    const grams = gramsFor(row.food, quantity, row.unit);
    const factor = grams / 100;
    const kcal = row.food.kcalPer100 * factor;
    const protein = row.food.proteinPer100 * factor;
    const carbs = row.food.carbsPer100 * factor;
    const fat = row.food.fatPer100 * factor;

    totalGrams += grams;
    totalKcal += kcal;
    totalProtein += protein;
    totalCarbs += carbs;
    totalFat += fat;

    return { kcal };
  });

  const per100 =
    totalGrams > 0
      ? {
          kcal: (totalKcal * 100) / totalGrams,
          protein: (totalProtein * 100) / totalGrams,
          carbs: (totalCarbs * 100) / totalGrams,
          fat: (totalFat * 100) / totalGrams,
        }
      : null;

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);

    if (rows.some((row) => !(Number(row.quantity) > 0))) {
      setError('Chaque ingrédient doit avoir une quantité positive');
      return;
    }

    setSubmitting(true);
    try {
      const payload = {
        name,
        ingredients: rows.map((row) => ({
          foodId: row.food.id,
          quantity: Number(row.quantity),
          unit: row.unit,
        })),
      };
      if (isEditing) {
        await api.put(`/api/meals/${id}`, payload);
      } else {
        await api.post('/api/meals', payload);
      }
      navigate('/meals');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Une erreur est survenue');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className={styles.wrapper}>
      <form className={styles.card} onSubmit={handleSubmit}>
        <h1 className={styles.title}>{isEditing ? 'Modifier le repas' : 'Créer un repas'}</h1>
        {error && <div className={styles.error}>{error}</div>}

        <div className={styles.field}>
          <label htmlFor="name">Nom du repas</label>
          <input id="name" value={name} onChange={(e) => setName(e.target.value)} required autoFocus />
        </div>

        <div className={styles.field}>
          <label>Ingrédients</label>
          {rows.length === 0 ? (
            <p className={styles.empty}>Aucun ingrédient ajouté</p>
          ) : (
            <ul className={styles.rowList}>
              {rows.map((row, index) => (
                <li key={index} className={styles.row}>
                  <span className={styles.rowName}>{row.food.name}</span>
                  <div className={styles.rowControls}>
                    <input
                      className={styles.rowQty}
                      type="number"
                      min={0}
                      step="any"
                      value={row.quantity}
                      onChange={(e) => updateQuantity(index, e.target.value)}
                    />
                    <span className={styles.rowUnit}>{row.unit}</span>
                    <span className={styles.rowKcal}>{Math.round(rowContributions[index].kcal)} kcal</span>
                    <button
                      type="button"
                      className={styles.removeBtn}
                      onClick={() => removeRow(index)}
                      aria-label="Retirer"
                    >
                      ×
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          )}
          <button type="button" className={styles.addIngredientBtn} onClick={() => setPickerOpen(true)}>
            + Ajouter un aliment
          </button>
        </div>

        {per100 && (
          <div className={styles.preview}>
            <p className={styles.previewTitle}>Apports pour 100g de repas</p>
            <div className={styles.previewGrid}>
              <span>{Math.round(per100.kcal)} kcal</span>
              <span>{per100.protein.toFixed(1)} g protéines</span>
              <span>{per100.carbs.toFixed(1)} g glucides</span>
              <span>{per100.fat.toFixed(1)} g lipides</span>
            </div>
          </div>
        )}

        <div className={styles.actions}>
          <button type="button" className={styles.secondary} onClick={() => navigate('/meals')}>
            Annuler
          </button>
          <button type="submit" className={styles.primary} disabled={submitting || rows.length === 0}>
            {submitting ? 'Enregistrement…' : isEditing ? 'Enregistrer' : 'Créer'}
          </button>
        </div>
      </form>

      {pickerOpen && <IngredientPickerModal onClose={() => setPickerOpen(false)} onAdd={addRow} />}
    </div>
  );
}

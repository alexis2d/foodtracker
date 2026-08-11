import { useState, useEffect, type FormEvent } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { api, ApiError } from '../lib/api';
import type { Food, FoodUnit } from '../lib/types';
import styles from './CustomFoodForm.module.css';

export function CustomFoodFormPage() {
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const isEditing = id !== undefined;

  const foodQuery = useQuery({
    queryKey: ['custom-food', id],
    queryFn: () => api.get<Food>(`/api/custom-foods/${id}`),
    enabled: isEditing,
  });

  const [name, setName] = useState('');
  const [defaultUnit, setDefaultUnit] = useState<FoodUnit>('g');
  const [unitWeightGrams, setUnitWeightGrams] = useState('');
  const [kcal, setKcal] = useState('');
  const [protein, setProtein] = useState('');
  const [carbs, setCarbs] = useState('');
  const [fat, setFat] = useState('');
  const [fiber, setFiber] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (foodQuery.data) {
      const food = foodQuery.data;
      setName(food.name);
      setDefaultUnit(food.defaultUnit);
      setUnitWeightGrams(food.unitWeightGrams !== null ? String(food.unitWeightGrams) : '');
      setKcal(String(food.kcalPer100));
      setProtein(String(food.proteinPer100));
      setCarbs(String(food.carbsPer100));
      setFat(String(food.fatPer100));
      setFiber(food.fiberPer100 !== null ? String(food.fiberPer100) : '');
    }
  }, [foodQuery.data]);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const payload = {
        name,
        defaultUnit,
        unitWeightGrams: defaultUnit === 'unit' ? Number(unitWeightGrams) : null,
        kcalPer100: Number(kcal),
        proteinPer100: Number(protein),
        carbsPer100: Number(carbs),
        fatPer100: Number(fat),
        fiberPer100: fiber === '' ? null : Number(fiber),
      };
      if (isEditing) {
        await api.put(`/api/custom-foods/${id}`, payload);
      } else {
        await api.post('/api/custom-foods', payload);
      }
      navigate('/foods');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Une erreur est survenue');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className={styles.wrapper}>
      <form className={styles.card} onSubmit={handleSubmit}>
        <h1 className={styles.title}>{isEditing ? "Modifier l'aliment" : 'Nouvel aliment personnalisé'}</h1>
        {error && <div className={styles.error}>{error}</div>}

        <div className={styles.field}>
          <label htmlFor="name">Nom</label>
          <input id="name" value={name} onChange={(e) => setName(e.target.value)} required autoFocus />
        </div>

        <div className={styles.field}>
          <label htmlFor="unit">Unité de référence</label>
          <select id="unit" value={defaultUnit} onChange={(e) => setDefaultUnit(e.target.value as FoodUnit)}>
            <option value="g">Grammes (par 100g)</option>
            <option value="ml">Millilitres (par 100ml)</option>
            <option value="unit">Unité (ex: 1 œuf)</option>
          </select>
        </div>

        {defaultUnit === 'unit' && (
          <div className={styles.field}>
            <label htmlFor="unitWeight">Poids d'une unité (g)</label>
            <input
              id="unitWeight"
              type="number"
              min={0}
              step="any"
              value={unitWeightGrams}
              onChange={(e) => setUnitWeightGrams(e.target.value)}
              required
            />
          </div>
        )}

        <div className={styles.grid}>
          <div className={styles.field}>
            <label htmlFor="kcal">Calories (kcal/100{defaultUnit === 'unit' ? 'g' : defaultUnit})</label>
            <input
              id="kcal"
              type="number"
              min={0}
              step="any"
              value={kcal}
              onChange={(e) => setKcal(e.target.value)}
              required
            />
          </div>
          <div className={styles.field}>
            <label htmlFor="protein">Protéines (g)</label>
            <input
              id="protein"
              type="number"
              min={0}
              step="any"
              value={protein}
              onChange={(e) => setProtein(e.target.value)}
              required
            />
          </div>
          <div className={styles.field}>
            <label htmlFor="carbs">Glucides (g)</label>
            <input
              id="carbs"
              type="number"
              min={0}
              step="any"
              value={carbs}
              onChange={(e) => setCarbs(e.target.value)}
              required
            />
          </div>
          <div className={styles.field}>
            <label htmlFor="fat">Lipides (g)</label>
            <input
              id="fat"
              type="number"
              min={0}
              step="any"
              value={fat}
              onChange={(e) => setFat(e.target.value)}
              required
            />
          </div>
          <div className={styles.field}>
            <label htmlFor="fiber">Fibres (g, optionnel)</label>
            <input id="fiber" type="number" min={0} step="any" value={fiber} onChange={(e) => setFiber(e.target.value)} />
          </div>
        </div>

        <div className={styles.actions}>
          <button type="button" className={styles.secondary} onClick={() => navigate('/foods')}>
            Annuler
          </button>
          <button type="submit" className={styles.primary} disabled={submitting}>
            {submitting ? 'Enregistrement…' : isEditing ? 'Enregistrer' : 'Créer'}
          </button>
        </div>
      </form>
    </div>
  );
}

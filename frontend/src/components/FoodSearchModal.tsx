import { useState, useEffect, useRef } from 'react';
import { useMutation } from '@tanstack/react-query';
import { api } from '../lib/api';
import type { Food, MealType } from '../lib/types';
import styles from './FoodSearchModal.module.css';

interface Props {
  mealType: MealType;
  date: string;
  onClose: () => void;
  onAdded: () => void;
}

const SOURCE_LABELS: Record<Food['source'], string> = {
  custom: 'perso',
  off: 'Open Food Facts',
  seed: 'base',
};

export function FoodSearchModal({ mealType, date, onClose, onAdded }: Props) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<Food[]>([]);
  const [loading, setLoading] = useState(false);
  const [selected, setSelected] = useState<Food | null>(null);
  const [quantity, setQuantity] = useState('100');
  const debounceRef = useRef<number | undefined>(undefined);

  useEffect(() => {
    if (query.trim().length < 2) {
      setResults([]);
      return;
    }
    window.clearTimeout(debounceRef.current);
    debounceRef.current = window.setTimeout(() => {
      setLoading(true);
      api
        .get<{ results: Food[] }>(`/api/foods/search?q=${encodeURIComponent(query)}`)
        .then((data) => setResults(data.results))
        .finally(() => setLoading(false));
    }, 300);
    return () => window.clearTimeout(debounceRef.current);
  }, [query]);

  const addMutation = useMutation({
    mutationFn: async () => {
      if (!selected) return;
      let foodId = selected.id;
      if (foodId === null && selected.offId) {
        const materialized = await api.post<Food>(`/api/foods/from-off/${selected.offId}`);
        foodId = materialized.id;
      }
      await api.post('/api/diary', {
        foodId,
        quantity: Number(quantity),
        unit: selected.defaultUnit,
        mealType,
        consumedAt: date,
      });
    },
    onSuccess: () => onAdded(),
  });

  return (
    <div className={styles.overlay} onClick={onClose}>
      <div className={styles.modal} onClick={(e) => e.stopPropagation()}>
        <div className={styles.header}>
          <h2>Ajouter un aliment</h2>
          <button className={styles.closeBtn} onClick={onClose} aria-label="Fermer">
            ×
          </button>
        </div>

        {!selected && (
          <>
            <input
              className={styles.searchInput}
              type="text"
              placeholder="Rechercher un aliment…"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              autoFocus
            />
            {loading && <p className={styles.hint}>Recherche…</p>}
            <ul className={styles.results}>
              {results.map((food) => (
                <li
                  key={food.offId ?? food.id}
                  className={styles.resultItem}
                  onClick={() => setSelected(food)}
                >
                  <span className={styles.resultName}>{food.name}</span>
                  <span className={styles.resultMeta}>
                    {Math.round(food.kcalPer100)} kcal/100{food.defaultUnit}
                    <span className={styles.badge}>{SOURCE_LABELS[food.source]}</span>
                  </span>
                </li>
              ))}
            </ul>
            {!loading && query.trim().length >= 2 && results.length === 0 && (
              <p className={styles.hint}>Aucun résultat.</p>
            )}
          </>
        )}

        {selected && (
          <div className={styles.quantityForm}>
            <p className={styles.selectedName}>{selected.name}</p>
            <label className={styles.qtyField}>
              Quantité ({selected.defaultUnit})
              <input
                type="number"
                min={0}
                step="any"
                value={quantity}
                onChange={(e) => setQuantity(e.target.value)}
                autoFocus
              />
            </label>
            <p className={styles.hint}>
              ≈ {Math.round((selected.kcalPer100 * Number(quantity || 0)) / 100)} kcal
            </p>
            {addMutation.isError && <p className={styles.error}>Impossible d'ajouter cet aliment.</p>}
            <div className={styles.actions}>
              <button className={styles.secondary} onClick={() => setSelected(null)}>
                Retour
              </button>
              <button
                className={styles.primary}
                onClick={() => addMutation.mutate()}
                disabled={addMutation.isPending || !(Number(quantity) > 0)}
              >
                {addMutation.isPending ? 'Ajout…' : 'Ajouter'}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

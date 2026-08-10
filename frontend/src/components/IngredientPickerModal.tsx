import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { api } from '../lib/api';
import { useFoodSearch } from '../lib/useFoodSearch';
import type { Food, FoodUnit } from '../lib/types';
import styles from './FoodSearchModal.module.css';

interface Props {
  onClose: () => void;
  onAdd: (food: Food, quantity: number, unit: FoodUnit) => void;
}

const SOURCE_LABELS: Record<Food['source'], string> = {
  custom: 'perso',
  off: 'Open Food Facts',
  seed: 'base',
  meal: 'mon repas',
};

export function IngredientPickerModal({ onClose, onAdd }: Props) {
  const { query, setQuery, results, loading } = useFoodSearch();
  const [selected, setSelected] = useState<Food | null>(null);
  const [quantity, setQuantity] = useState('100');

  const addMutation = useMutation({
    mutationFn: async () => {
      if (!selected) return;
      let food = selected;
      if (food.id === null && food.offId) {
        food = await api.post<Food>(`/api/foods/from-off/${food.offId}`);
      }
      onAdd(food, Number(quantity), food.defaultUnit);
    },
    onSuccess: () => onClose(),
  });

  return (
    <div className={styles.overlay} onClick={onClose}>
      <div className={styles.modal} onClick={(e) => e.stopPropagation()}>
        <div className={styles.header}>
          <h2>Ajouter un ingrédient</h2>
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
            {addMutation.isError && <p className={styles.error}>Impossible d'ajouter cet ingrédient.</p>}
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

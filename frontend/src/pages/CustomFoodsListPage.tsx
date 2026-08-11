import { Link } from 'react-router-dom';
import { useQuery, useQueryClient, useMutation } from '@tanstack/react-query';
import { api } from '../lib/api';
import type { Food } from '../lib/types';
import styles from './CustomFoodsListPage.module.css';

export function CustomFoodsListPage() {
  const queryClient = useQueryClient();

  const foodsQuery = useQuery({
    queryKey: ['custom-foods'],
    queryFn: () => api.get<{ results: Food[] }>('/api/custom-foods'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/custom-foods/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['custom-foods'] }),
  });

  const foods = foodsQuery.data?.results ?? [];

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <h1 className={styles.title}>Mes aliments</h1>
        <Link className={styles.backLink} to="/">
          Retour au tableau de bord
        </Link>
      </header>

      <Link className={styles.newFoodLink} to="/foods/new">
        + Créer un aliment personnalisé
      </Link>

      {foodsQuery.isLoading && <p>Chargement…</p>}

      {!foodsQuery.isLoading && foods.length === 0 && (
        <p className={styles.empty}>Aucun aliment personnalisé pour le moment.</p>
      )}

      <ul className={styles.foodList}>
        {foods.map((food) => (
          <li key={food.id} className={styles.food}>
            <div className={styles.foodInfo}>
              <span className={styles.foodName}>{food.name}</span>
              <span className={styles.foodMeta}>
                {Math.round(food.kcalPer100)} kcal/100{food.defaultUnit === 'unit' ? 'g' : food.defaultUnit}
              </span>
            </div>
            <div className={styles.foodActions}>
              <Link className={styles.editLink} to={`/foods/${food.id}/edit`}>
                Modifier
              </Link>
              <button
                className={styles.deleteBtn}
                onClick={() => {
                  if (food.id !== null && confirm(`Supprimer l'aliment "${food.name}" ?`)) {
                    deleteMutation.mutate(food.id);
                  }
                }}
                aria-label="Supprimer"
              >
                ×
              </button>
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}

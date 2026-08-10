import { Link } from 'react-router-dom';
import { useQuery, useQueryClient, useMutation } from '@tanstack/react-query';
import { api } from '../lib/api';
import type { Meal } from '../lib/types';
import styles from './MealsListPage.module.css';

export function MealsListPage() {
  const queryClient = useQueryClient();

  const mealsQuery = useQuery({
    queryKey: ['meals'],
    queryFn: () => api.get<{ results: Meal[] }>('/api/meals'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/meals/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['meals'] }),
  });

  const meals = mealsQuery.data?.results ?? [];

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <h1 className={styles.title}>Mes repas</h1>
        <Link className={styles.backLink} to="/">
          Retour au tableau de bord
        </Link>
      </header>

      <Link className={styles.newMealLink} to="/meals/new">
        + Créer un repas
      </Link>

      {mealsQuery.isLoading && <p>Chargement…</p>}

      {!mealsQuery.isLoading && meals.length === 0 && (
        <p className={styles.empty}>Aucun repas composé pour le moment.</p>
      )}

      <ul className={styles.mealList}>
        {meals.map((meal) => (
          <li key={meal.id} className={styles.meal}>
            <div className={styles.mealInfo}>
              <span className={styles.mealName}>{meal.name}</span>
              <span className={styles.mealMeta}>
                {Math.round(meal.food.kcalPer100)} kcal/100g · {meal.ingredients.length} ingrédient
                {meal.ingredients.length > 1 ? 's' : ''}
              </span>
            </div>
            <div className={styles.mealActions}>
              <Link className={styles.editLink} to={`/meals/${meal.id}/edit`}>
                Modifier
              </Link>
              <button
                className={styles.deleteBtn}
                onClick={() => {
                  if (confirm(`Supprimer le repas "${meal.name}" ?`)) {
                    deleteMutation.mutate(meal.id);
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

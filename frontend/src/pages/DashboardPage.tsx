import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery, useQueryClient, useMutation } from '@tanstack/react-query';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';
import { MEAL_TYPES, MEAL_LABELS, type DailySummary, type MealType } from '../lib/types';
import { FoodSearchModal } from '../components/FoodSearchModal';
import { DailySummaryBar } from '../components/DailySummaryBar';
import styles from './Dashboard.module.css';

function todayStr(): string {
  return new Date().toISOString().slice(0, 10);
}

export function DashboardPage() {
  const { user, logout } = useAuth();
  const queryClient = useQueryClient();
  const [date, setDate] = useState(todayStr());
  const [activeMeal, setActiveMeal] = useState<MealType | null>(null);

  const summaryQuery = useQuery({
    queryKey: ['diary-summary', date],
    queryFn: () => api.get<DailySummary>(`/api/diary/summary?date=${date}`),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/diary/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['diary-summary', date] }),
  });

  const summary = summaryQuery.data;

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.appName}>FoodTracker</h1>
          <span className={styles.email}>{user?.email}</span>
        </div>
        <div className={styles.headerActions}>
          <input
            className={styles.dateInput}
            type="date"
            value={date}
            onChange={(e) => setDate(e.target.value)}
          />
          <Link className={styles.logoutBtn} to="/profile">
            Profil
          </Link>
          <button className={styles.logoutBtn} onClick={() => logout()}>
            Déconnexion
          </button>
        </div>
      </header>

      {summary && (
        <DailySummaryBar
          totals={summary.totals}
          goalKcal={summary.goal.kcal}
          remainingKcal={summary.remaining.kcal}
        />
      )}

      {summaryQuery.isLoading && <p>Chargement…</p>}

      {summary &&
        MEAL_TYPES.map((meal) => (
          <section key={meal} className={styles.mealSection}>
            <div className={styles.mealHeader}>
              <h2>{MEAL_LABELS[meal]}</h2>
              <button className={styles.addBtn} onClick={() => setActiveMeal(meal)}>
                + Ajouter
              </button>
            </div>
            {summary.entriesByMeal[meal].length === 0 ? (
              <p className={styles.empty}>Aucun aliment ajouté</p>
            ) : (
              <ul className={styles.entryList}>
                {summary.entriesByMeal[meal].map((entry) => (
                  <li key={entry.id} className={styles.entry}>
                    <span className={styles.entryName}>{entry.food.name}</span>
                    <span className={styles.entryQty}>
                      {entry.quantity}
                      {entry.unit}
                    </span>
                    <span className={styles.entryKcal}>{Math.round(entry.kcal)} kcal</span>
                    <button
                      className={styles.deleteBtn}
                      onClick={() => deleteMutation.mutate(entry.id)}
                      aria-label="Supprimer"
                    >
                      ×
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </section>
        ))}

      <Link className={styles.newFoodLink} to="/foods/new">
        + Créer un aliment personnalisé
      </Link>

      {activeMeal && (
        <FoodSearchModal
          mealType={activeMeal}
          date={date}
          onClose={() => setActiveMeal(null)}
          onAdded={() => {
            queryClient.invalidateQueries({ queryKey: ['diary-summary', date] });
            setActiveMeal(null);
          }}
        />
      )}
    </div>
  );
}

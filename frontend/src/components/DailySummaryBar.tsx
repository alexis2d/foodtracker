import styles from './DailySummaryBar.module.css';

interface Props {
  totals: { kcal: number; protein: number; carbs: number; fat: number };
  goalKcal: number;
  remainingKcal: number;
}

export function DailySummaryBar({ totals, goalKcal, remainingKcal }: Props) {
  const pct = goalKcal > 0 ? Math.min(100, Math.round((totals.kcal / goalKcal) * 100)) : 0;
  const over = remainingKcal < 0;

  return (
    <div className={styles.bar}>
      <div className={styles.kcalRow}>
        <span className={styles.kcalValue}>
          <strong>{Math.round(totals.kcal)}</strong> / {goalKcal} kcal
        </span>
        <span className={over ? styles.over : styles.remaining}>
          {over ? `${Math.round(-remainingKcal)} kcal au-dessus` : `${Math.round(remainingKcal)} kcal restantes`}
        </span>
      </div>
      <div className={styles.progressTrack}>
        <div
          className={over ? styles.progressFillOver : styles.progressFill}
          style={{ width: `${pct}%` }}
        />
      </div>
      <div className={styles.macros}>
        <span>Protéines {Math.round(totals.protein)}g</span>
        <span>Glucides {Math.round(totals.carbs)}g</span>
        <span>Lipides {Math.round(totals.fat)}g</span>
      </div>
    </div>
  );
}

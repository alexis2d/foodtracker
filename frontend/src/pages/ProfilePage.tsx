import { useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { api, ApiError } from '../lib/api';
import { useAuth } from '../lib/auth';
import { ACTIVITY_LEVELS, ACTIVITY_LEVEL_LABELS, type ActivityLevel, type Sex } from '../lib/types';
import styles from './ProfileForm.module.css';

function ChangePasswordCard() {
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setMessage(null);
    setSubmitting(true);
    try {
      await api.post('/api/change-password', { currentPassword, newPassword });
      setMessage('Un email de confirmation vient de vous être envoyé. Le changement prendra effet une fois le lien cliqué.');
      setCurrentPassword('');
      setNewPassword('');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Une erreur est survenue');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form className={styles.card} onSubmit={handleSubmit}>
      <h1 className={styles.title}>Sécurité</h1>
      <p className={styles.hint}>
        Le nouveau mot de passe ne sera actif qu'après confirmation via le lien qui vous sera envoyé par email.
      </p>
      {error && <div className={styles.error}>{error}</div>}
      {message && <p className={styles.note}>{message}</p>}
      <div className={styles.field}>
        <label htmlFor="currentPassword">Mot de passe actuel</label>
        <input
          id="currentPassword"
          type="password"
          value={currentPassword}
          onChange={(e) => setCurrentPassword(e.target.value)}
          required
        />
      </div>
      <div className={styles.field}>
        <label htmlFor="newPassword">Nouveau mot de passe</label>
        <input
          id="newPassword"
          type="password"
          value={newPassword}
          onChange={(e) => setNewPassword(e.target.value)}
          minLength={8}
          required
        />
      </div>
      <div className={styles.actions}>
        <button type="submit" className={styles.primary} disabled={submitting}>
          {submitting ? 'Envoi…' : 'Changer le mot de passe'}
        </button>
      </div>
    </form>
  );
}

export function ProfilePage() {
  const navigate = useNavigate();
  const { user, refreshUser } = useAuth();

  const [height, setHeight] = useState(user?.heightCm != null ? String(user.heightCm) : '');
  const [weight, setWeight] = useState(user?.weightKg != null ? String(user.weightKg) : '');
  const [age, setAge] = useState(user?.age != null ? String(user.age) : '');
  const [sex, setSex] = useState<Sex | ''>(user?.sex ?? '');
  const [activityLevel, setActivityLevel] = useState<ActivityLevel | ''>(user?.activityLevel ?? '');
  const [calorieGoal, setCalorieGoal] = useState(String(user?.dailyCalorieGoal ?? 2000));

  const [calculatedNote, setCalculatedNote] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [calculating, setCalculating] = useState(false);
  const [saving, setSaving] = useState(false);

  const canCalculate = height !== '' && weight !== '' && age !== '' && sex !== '' && activityLevel !== '';

  async function handleCalculate() {
    setError(null);
    setCalculatedNote(null);
    setCalculating(true);
    try {
      const result = await api.post<{ dailyCalorieGoal: number }>('/api/profile/calculate-goal', {
        heightCm: Number(height),
        weightKg: Number(weight),
        age: Number(age),
        sex,
        activityLevel,
      });
      setCalorieGoal(String(result.dailyCalorieGoal));
      setCalculatedNote(`Objectif calculé : ${result.dailyCalorieGoal} kcal/jour. Vous pouvez l'ajuster avant d'enregistrer.`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Une erreur est survenue');
    } finally {
      setCalculating(false);
    }
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setSaving(true);
    try {
      await api.put('/api/profile', {
        heightCm: height === '' ? null : Number(height),
        weightKg: weight === '' ? null : Number(weight),
        age: age === '' ? null : Number(age),
        sex: sex === '' ? null : sex,
        activityLevel: activityLevel === '' ? null : activityLevel,
        dailyCalorieGoal: Number(calorieGoal),
      });
      await refreshUser();
      navigate('/');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Une erreur est survenue');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className={styles.wrapper}>
      <div className={styles.stack}>
      <form className={styles.card} onSubmit={handleSubmit}>
        <h1 className={styles.title}>Mon profil &amp; objectif calorique</h1>
        {error && <div className={styles.error}>{error}</div>}

        <p className={styles.hint}>
          Renseignez votre profil pour obtenir une estimation de votre objectif calorique quotidien, ou définissez-le
          directement à la main plus bas.
        </p>

        <div className={styles.grid}>
          <div className={styles.field}>
            <label htmlFor="height">Taille (cm)</label>
            <input
              id="height"
              type="number"
              min={50}
              max={250}
              step="any"
              value={height}
              onChange={(e) => setHeight(e.target.value)}
            />
          </div>
          <div className={styles.field}>
            <label htmlFor="weight">Poids (kg)</label>
            <input
              id="weight"
              type="number"
              min={20}
              max={300}
              step="any"
              value={weight}
              onChange={(e) => setWeight(e.target.value)}
            />
          </div>
          <div className={styles.field}>
            <label htmlFor="age">Âge</label>
            <input
              id="age"
              type="number"
              min={10}
              max={120}
              step="1"
              value={age}
              onChange={(e) => setAge(e.target.value)}
            />
          </div>
          <div className={styles.field}>
            <label htmlFor="sex">Sexe</label>
            <select id="sex" value={sex} onChange={(e) => setSex(e.target.value as Sex | '')}>
              <option value="">Non renseigné</option>
              <option value="male">Homme</option>
              <option value="female">Femme</option>
            </select>
          </div>
        </div>

        <div className={styles.field}>
          <label htmlFor="activityLevel">Niveau d'activité physique</label>
          <select
            id="activityLevel"
            value={activityLevel}
            onChange={(e) => setActivityLevel(e.target.value as ActivityLevel | '')}
          >
            <option value="">Non renseigné</option>
            {ACTIVITY_LEVELS.map((level) => (
              <option key={level} value={level}>
                {ACTIVITY_LEVEL_LABELS[level]}
              </option>
            ))}
          </select>
        </div>

        <div className={styles.actions}>
          <button
            type="button"
            className={styles.secondary}
            onClick={handleCalculate}
            disabled={!canCalculate || calculating}
          >
            {calculating ? 'Calcul…' : 'Calculer mon objectif'}
          </button>
        </div>

        {calculatedNote && <p className={styles.note}>{calculatedNote}</p>}

        <div className={styles.field}>
          <label htmlFor="calorieGoal">Objectif calorique quotidien (kcal)</label>
          <input
            id="calorieGoal"
            type="number"
            min={500}
            max={10000}
            step="1"
            value={calorieGoal}
            onChange={(e) => setCalorieGoal(e.target.value)}
            required
          />
        </div>

        <div className={styles.actions}>
          <button type="button" className={styles.secondary} onClick={() => navigate('/')}>
            Annuler
          </button>
          <button type="submit" className={styles.primary} disabled={saving}>
            {saving ? 'Enregistrement…' : 'Enregistrer'}
          </button>
        </div>
      </form>

      <ChangePasswordCard />
      </div>
    </div>
  );
}

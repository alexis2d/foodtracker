import { Routes, Route, Navigate } from 'react-router-dom';
import { ProtectedRoute } from './components/ProtectedRoute';
import { LoginPage } from './pages/LoginPage';
import { RegisterPage } from './pages/RegisterPage';
import { VerifyEmailPage } from './pages/VerifyEmailPage';
import { ForgotPasswordPage } from './pages/ForgotPasswordPage';
import { ResetPasswordPage } from './pages/ResetPasswordPage';
import { ConfirmPasswordChangePage } from './pages/ConfirmPasswordChangePage';
import { DashboardPage } from './pages/DashboardPage';
import { CustomFoodFormPage } from './pages/CustomFoodFormPage';
import { ProfilePage } from './pages/ProfilePage';
import { MealsListPage } from './pages/MealsListPage';
import { MealFormPage } from './pages/MealFormPage';

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/verify-email" element={<VerifyEmailPage />} />
      <Route path="/forgot-password" element={<ForgotPasswordPage />} />
      <Route path="/reset-password" element={<ResetPasswordPage />} />
      <Route path="/confirm-password-change" element={<ConfirmPasswordChangePage />} />
      <Route element={<ProtectedRoute />}>
        <Route path="/" element={<DashboardPage />} />
        <Route path="/foods/new" element={<CustomFoodFormPage />} />
        <Route path="/profile" element={<ProfilePage />} />
        <Route path="/meals" element={<MealsListPage />} />
        <Route path="/meals/new" element={<MealFormPage />} />
        <Route path="/meals/:id/edit" element={<MealFormPage />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}

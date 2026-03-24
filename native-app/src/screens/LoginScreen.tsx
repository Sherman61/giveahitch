import { FC, useState } from 'react';
import { View, Text, TextInput, StyleSheet } from 'react-native';
import { PrimaryButton } from '@/components/PrimaryButton';
import { Card } from '@/components/Card';
import { login, AuthResponse } from '@/api/auth';
import { UserProfile } from '@/types/user';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';
import { PageHeader } from '@/components/PageHeader';

interface Props {
  currentUser: UserProfile | null;
  onLoginSuccess: (auth: AuthResponse) => void;
  onLogout: () => void;
  onBack?: () => void;
}

export const LoginScreen: FC<Props> = ({ currentUser, onLoginSuccess, onLogout, onBack }) => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async () => {
    setLoading(true);
    setError(null);
    try {
      const auth = await login(email.trim(), password);
      onLoginSuccess(auth);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Login failed');
    } finally {
      setLoading(false);
    }
  };

  if (currentUser) {
    return (
      <View style={styles.container}>
        <PageHeader title="Account" subtitle="You are already signed in." onBack={onBack} />
        <Card>
          <Text style={styles.title}>You are signed in</Text>
          <Text style={styles.subtitle}>{currentUser.email}</Text>
          <PrimaryButton label="Log out" onPress={onLogout} />
        </Card>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <PageHeader
        title="Account"
        subtitle="Sign in to manage your rides, messages, and settings."
        onBack={onBack}
      />
      <Card>
        <Text style={styles.title}>Log in</Text>
        <Text style={styles.subtitle}>Sign in with your GlitchaHitch credentials.</Text>
        <TextInput
          style={styles.input}
          placeholder="Email"
          autoCapitalize="none"
          keyboardType="email-address"
          value={email}
          onChangeText={setEmail}
        />
        <TextInput
          style={styles.input}
          placeholder="Password"
          secureTextEntry
          value={password}
          onChangeText={setPassword}
        />
        {error && <Text style={styles.error}>{error}</Text>}
        <PrimaryButton label={loading ? 'Logging in...' : 'Log in'} onPress={handleSubmit} />
      </Card>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: palette.background,
    padding: spacing.lg,
    justifyContent: 'center',
  },
  title: {
    fontSize: 22,
    fontWeight: '700',
    marginBottom: spacing.xs,
  },
  subtitle: {
    color: palette.muted,
    marginBottom: spacing.md,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ced4da',
    borderRadius: 10,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    marginBottom: spacing.sm,
    backgroundColor: palette.surface,
  },
  error: {
    color: palette.danger,
    marginBottom: spacing.sm,
  },
});

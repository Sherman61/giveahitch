import { FC, useState } from 'react';
import { View, Text, TextInput, StyleSheet } from 'react-native';
import { PrimaryButton } from '@/components/PrimaryButton';
import { Card } from '@/components/Card';
import { login, AuthResponse, UserProfile } from '@/api/auth';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';

interface Props {
  currentUser: UserProfile | null;
  onLoginSuccess: (auth: AuthResponse) => void;
  onLogout: () => void;
}

export const LoginScreen: FC<Props> = ({ currentUser, onLoginSuccess, onLogout }) => {
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
        <Card>
          <Text style={styles.title}>You are signed in</Text>
          <Text style={styles.subtitle}>{currentUser.email}</Text>
          <PrimaryButton label="Log Out" onPress={onLogout} />
        </Card>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <Card>
        <Text style={styles.title}>Log In</Text>
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
        <PrimaryButton label={loading ? 'Logging in...' : 'Log In'} onPress={handleSubmit} />
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

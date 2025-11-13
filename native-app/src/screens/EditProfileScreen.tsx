import { FC, useEffect, useState } from 'react';
import { ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { fetchProfileDetails, updateProfileContact } from '@/api/profile';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';
import { PrimaryButton } from '@/components/PrimaryButton';

export const EditProfileScreen: FC = () => {
  const [displayName, setDisplayName] = useState('');
  const [phone, setPhone] = useState('');
  const [whatsapp, setWhatsapp] = useState('');
  const [contactPrivacy, setContactPrivacy] = useState(1);
  const [messagePrivacy, setMessagePrivacy] = useState(1);
  const [status, setStatus] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    fetchProfileDetails()
      .then((data) => {
        setDisplayName(data.displayName ?? data.name);
        setPhone(data.contact.phone ?? '');
        setWhatsapp(data.contact.whatsapp ?? '');
        setContactPrivacy(data.contactPrivacy ?? 1);
        setMessagePrivacy(data.messagePrivacy ?? 1);
      })
      .catch((err) => setError(err instanceof Error ? err.message : 'Unable to load profile'));
  }, []);

  const handleSave = async () => {
    setLoading(true);
    setStatus(null);
    setError(null);
    try {
      await updateProfileContact({
        display_name: displayName,
        phone,
        whatsapp,
        contact_privacy: contactPrivacy,
        message_privacy: messagePrivacy,
      });
      setStatus('Saved!');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to update profile');
    } finally {
      setLoading(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.title}>Edit profile</Text>
      <Text style={styles.subtitle}>Update your display name and contact details.</Text>

      <Text style={styles.label}>Display name</Text>
      <TextInput style={styles.input} value={displayName} onChangeText={setDisplayName} />

      <Text style={styles.label}>Phone</Text>
      <TextInput style={styles.input} value={phone} onChangeText={setPhone} keyboardType="phone-pad" />

      <Text style={styles.label}>WhatsApp</Text>
      <TextInput style={styles.input} value={whatsapp} onChangeText={setWhatsapp} keyboardType="phone-pad" />

      <View style={styles.row}>
        <View style={styles.column}>
          <Text style={styles.label}>Contact privacy</Text>
          <TextInput
            style={styles.input}
            value={String(contactPrivacy)}
            onChangeText={(value) => setContactPrivacy(Number(value) || 1)}
          />
        </View>
        <View style={styles.column}>
          <Text style={styles.label}>Message privacy</Text>
          <TextInput
            style={styles.input}
            value={String(messagePrivacy)}
            onChangeText={(value) => setMessagePrivacy(Number(value) || 1)}
          />
        </View>
      </View>

      {error && <Text style={styles.error}>{error}</Text>}
      {status && <Text style={styles.success}>{status}</Text>}

      <PrimaryButton label={loading ? 'Saving...' : 'Save changes'} onPress={handleSave} />
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: palette.background,
  },
  content: {
    padding: spacing.lg,
    gap: spacing.md,
    paddingBottom: spacing.xl,
  },
  title: {
    fontSize: 26,
    fontWeight: '700',
  },
  subtitle: {
    color: palette.muted,
  },
  label: {
    fontSize: 12,
    textTransform: 'uppercase',
    color: palette.muted,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ced4da',
    borderRadius: 10,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    backgroundColor: '#fff',
    marginBottom: spacing.sm,
  },
  row: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  column: {
    flex: 1,
  },
  error: {
    color: palette.danger,
  },
  success: {
    color: palette.primary,
  },
});

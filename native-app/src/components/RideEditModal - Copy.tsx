import { FC, useEffect, useState } from 'react';
import {
  KeyboardAvoidingView,
  Modal,
  Platform,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  TouchableOpacity,
  View,
  Alert,
} from 'react-native';
import dayjs from 'dayjs';
import { RideSummary } from '@/types/rides';
import { updateRide } from '@/api/rides';
import { PrimaryButton } from './PrimaryButton';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';
import { DateTimeField } from './DateTimeField';

interface Props {
  ride: RideSummary | null;
  visible: boolean;
  onClose: () => void;
  onSaved: () => void;
}

const toIso = (value?: string | null) => {
  if (!value) return '';
  const parsed = dayjs(value);
  return parsed.isValid() ? parsed.toDate().toISOString() : '';
};

export const RideEditModal: FC<Props> = ({ ride, visible, onClose, onSaved }) => {
  const [rideType, setRideType] = useState<'offer' | 'request'>('offer');
  const [fromText, setFromText] = useState('');
  const [toText, setToText] = useState('');
  const [departure, setDeparture] = useState('');
  const [seats, setSeats] = useState('1');
  const [packageOnly, setPackageOnly] = useState(false);
  const [phone, setPhone] = useState('');
  const [whatsapp, setWhatsapp] = useState('');
  const [notes, setNotes] = useState('');
  const [formError, setFormError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (ride && visible) {
      setRideType(ride.type);
      setFromText(ride.origin);
      setToText(ride.destination);
      setDeparture(toIso(ride.departureTime));
      setSeats(String(ride.seats ?? 1));
      setPackageOnly(Boolean(ride.packageOnly || ride.seats === 0));
      setPhone(ride.phone ?? '');
      setWhatsapp(ride.whatsapp ?? '');
      setNotes(ride.note ?? '');
      setFormError(null);
    }
  }, [ride, visible]);

  if (!ride) {
    return null;
  }

  const handleTogglePackageOnly = (value: boolean) => {
    setPackageOnly(value);
    if (value) {
      setSeats('0');
    } else if (seats === '0') {
      setSeats('1');
    }
  };

  const handleSave = async () => {
    if (!ride) return;
    const trimmedFrom = fromText.trim();
    const trimmedTo = toText.trim();
    const trimmedPhone = phone.trim();
    const trimmedWhatsapp = whatsapp.trim();
    const trimmedNotes = notes.trim();

    if (!trimmedFrom || !trimmedTo) {
      setFormError('Origin and destination are required.');
      return;
    }
    if (!trimmedPhone && !trimmedWhatsapp) {
      setFormError('Provide at least one contact method.');
      return;
    }

    const seatValue = Math.max(0, Number(seats) || 0);
    const formattedDeparture = departure ? dayjs(departure).format('YYYY-MM-DDTHH:mm') : undefined;

    try {
      setSaving(true);
      setFormError(null);
      await updateRide({
        id: ride.id,
        type: rideType,
        from_text: trimmedFrom,
        to_text: trimmedTo,
        ride_datetime: formattedDeparture,
        ride_end_datetime: null,
        seats: packageOnly ? 0 : seatValue,
        phone: trimmedPhone || undefined,
        whatsapp: trimmedWhatsapp || undefined,
        note: trimmedNotes || undefined,
        package_only: packageOnly ? 1 : 0,
      });
      Alert.alert('Ride updated', 'Your ride details have been saved.');
      onSaved();
      onClose();
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to update ride.';
      setFormError(message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal visible={visible} animationType="slide" presentationStyle="formSheet" onRequestClose={onClose}>
      <SafeAreaView style={styles.modalRoot}>
        <View style={styles.modalHeader}>
          <Text style={styles.modalTitle}>Edit ride</Text>
          <TouchableOpacity onPress={onClose}>
            <Text style={styles.closeText}>Close</Text>
          </TouchableOpacity>
        </View>
        <KeyboardAvoidingView
          style={styles.flex}
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
          keyboardVerticalOffset={80}
        >
          <ScrollView contentContainerStyle={styles.content}>
            <Text style={styles.helper}>Update ride info and notify responders instantly.</Text>
            <View style={styles.typeRow}>
              {(['offer', 'request'] as const).map((type) => (
                <TouchableOpacity
                  key={type}
                  style={[styles.typeOption, rideType === type && styles.typeOptionActive]}
                  onPress={() => setRideType(type)}
                >
                  <Text style={[styles.typeLabel, rideType === type && styles.typeLabelActive]}>
                    {type === 'offer' ? 'Offer seat' : 'Request ride'}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>

            <TextInput
              style={styles.input}
              placeholder="From"
              value={fromText}
              onChangeText={setFromText}
            />
            <TextInput
              style={styles.input}
              placeholder="To"
              value={toText}
              onChangeText={setToText}
            />

            <DateTimeField
              label="Start time"
              value={departure}
              placeholder="Pick a departure time"
              onChange={setDeparture}
            />

            <View style={styles.row}>
              <View style={styles.column}>
                <Text style={styles.label}>Seats</Text>
                <TextInput
                  style={styles.input}
                  keyboardType="numeric"
                  value={seats}
                  onChangeText={setSeats}
                  editable={!packageOnly}
                />
              </View>
              <View style={[styles.column, styles.packageColumn]}>
                <Text style={styles.label}>Packages only</Text>
                <Switch value={packageOnly} onValueChange={handleTogglePackageOnly} />
              </View>
            </View>

            <TextInput
              style={styles.input}
              placeholder="Phone (+1 718 555 1234)"
              value={phone}
              onChangeText={setPhone}
              keyboardType="phone-pad"
            />
            <TextInput
              style={styles.input}
              placeholder="WhatsApp (+1 347 555 7890)"
              value={whatsapp}
              onChangeText={setWhatsapp}
              keyboardType="phone-pad"
            />
            <TextInput
              style={[styles.input, styles.multiline]}
              placeholder="Notes (optional)"
              value={notes}
              onChangeText={setNotes}
              multiline
              numberOfLines={3}
            />

            {formError && <Text style={styles.error}>{formError}</Text>}

            <PrimaryButton label={saving ? 'Saving…' : 'Save changes'} onPress={handleSave} />
          </ScrollView>
        </KeyboardAvoidingView>
      </SafeAreaView>
    </Modal>
  );
};

const styles = StyleSheet.create({
  modalRoot: {
    flex: 1,
    backgroundColor: palette.background,
  },
  flex: {
    flex: 1,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.sm,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '700',
  },
  closeText: {
    color: palette.primary,
    fontWeight: '600',
  },
  content: {
    padding: spacing.lg,
    gap: spacing.sm,
    paddingBottom: spacing.xl,
  },
  helper: {
    color: palette.muted,
  },
  typeRow: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  typeOption: {
    flex: 1,
    paddingVertical: spacing.sm,
    borderWidth: 1,
    borderColor: '#ced4da',
    borderRadius: 10,
    alignItems: 'center',
    backgroundColor: '#f8f9fa',
  },
  typeOptionActive: {
    borderColor: palette.primary,
    backgroundColor: '#e7f0ff',
  },
  typeLabel: {
    color: palette.muted,
    fontWeight: '600',
  },
  typeLabelActive: {
    color: palette.primary,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ced4da',
    borderRadius: 10,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    backgroundColor: palette.surface,
  },
  multiline: {
    minHeight: 80,
    textAlignVertical: 'top',
  },
  row: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  column: {
    flex: 1,
  },
  packageColumn: {
    justifyContent: 'center',
    alignItems: 'flex-start',
  },
  label: {
    fontSize: 12,
    textTransform: 'uppercase',
    color: palette.muted,
    marginBottom: 4,
  },
  error: {
    color: palette.danger,
  },
});

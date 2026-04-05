import { FC, useState } from 'react';
import { View, Text, TouchableOpacity, StyleSheet, Platform } from 'react-native';
import DateTimePicker, {
  DateTimePickerEvent,
  DateTimePickerAndroid,
} from '@react-native-community/datetimepicker';
import dayjs from 'dayjs';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';

interface Props {
  label: string;
  value: string;
  placeholder: string;
  onChange: (isoString: string) => void;
}

export const DateTimeField: FC<Props> = ({ label, value, placeholder, onChange }) => {
  const [showIosPicker, setShowIosPicker] = useState(false);

  const displayValue = value ? dayjs(value).format('MMM D, YYYY h:mm A') : placeholder;
  const date = value ? new Date(value) : new Date();

  const handleIosChange = (_event: DateTimePickerEvent, selectedDate?: Date) => {
    if (selectedDate) {
      onChange(selectedDate.toISOString());
    }
  };

  const openPicker = () => {
    if (Platform.OS === 'android') {
      DateTimePickerAndroid.open({
        value: date,
        mode: 'date',
        onChange: (event, selectedDate) => {
          if (event.type !== 'set' || !selectedDate) return;
          DateTimePickerAndroid.open({
            value: selectedDate,
            mode: 'time',
            onChange: (timeEvent, selectedTime) => {
              if (timeEvent.type !== 'set' || !selectedTime) return;
              const combined = new Date(selectedDate);
              combined.setHours(selectedTime.getHours(), selectedTime.getMinutes(), 0, 0);
              onChange(combined.toISOString());
            },
          });
        },
      });
      return;
    }
    setShowIosPicker((prev) => !prev);
  };

  return (
    <View>
      <Text style={styles.label}>{label}</Text>
      <TouchableOpacity
        style={[styles.input, !value && styles.placeholder]}
        onPress={openPicker}
      >
        <Text style={value ? styles.valueText : styles.placeholderText}>{displayValue}</Text>
      </TouchableOpacity>
      {Platform.OS === 'ios' && showIosPicker && (
        <DateTimePicker
          value={date}
          mode="datetime"
          display="spinner"
          onChange={handleIosChange}
        />
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  label: {
    fontSize: 12,
    textTransform: 'uppercase',
    color: palette.muted,
    marginBottom: 4,
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
  placeholder: {
    borderStyle: 'dashed',
  },
  valueText: {
    color: palette.text,
  },
  placeholderText: {
    color: palette.muted,
  },
});

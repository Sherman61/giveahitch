import { FC, ReactNode } from 'react';
import { TouchableOpacity, Text, StyleSheet, ViewStyle } from 'react-native';
import { palette } from '@/constants/colors';
import { spacing, radius } from '@/constants/layout';

interface Props {
  label: string;
  onPress: () => void | Promise<void>;
  variant?: 'primary' | 'secondary';
  accessory?: ReactNode;
  style?: ViewStyle;
}

export const PrimaryButton: FC<Props> = ({
  label,
  onPress,
  variant = 'primary',
  accessory,
  style,
}) => {
  return (
    <TouchableOpacity
      style={[styles.button, variant === 'secondary' && styles.secondary, style]}
      onPress={onPress}
      activeOpacity={0.85}
    >
      <Text style={styles.text}>{label}</Text>
      {accessory}
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  button: {
    backgroundColor: palette.primary,
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.lg,
    borderRadius: radius.md,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  secondary: {
    backgroundColor: palette.primaryDark,
  },
  text: {
    color: '#fff',
    fontWeight: '600',
  },
});

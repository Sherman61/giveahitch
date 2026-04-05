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
  const isSecondary = variant === 'secondary';

  return (
    <TouchableOpacity
      style={[styles.button, isSecondary && styles.secondary, style]}
      onPress={onPress}
      activeOpacity={0.85}
    >
      <Text style={[styles.text, isSecondary && styles.secondaryText]}>{label}</Text>
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
    borderWidth: 1,
    borderColor: palette.primary,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  secondary: {
    backgroundColor: '#ffffff',
    borderColor: '#d6dee7',
  },
  text: {
    color: '#fff',
    fontWeight: '600',
  },
  secondaryText: {
    color: palette.text,
  },
});

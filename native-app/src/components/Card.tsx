import { FC, ReactNode } from 'react';
import { View, StyleSheet } from 'react-native';
import { palette } from '@/constants/colors';
import { spacing, radius, shadow } from '@/constants/layout';

interface Props {
  children: ReactNode;
}

export const Card: FC<Props> = ({ children }) => {
  return <View style={styles.card}>{children}</View>;
};

const styles = StyleSheet.create({
  card: {
    backgroundColor: palette.surface,
    borderRadius: radius.md,
    padding: spacing.lg,
    marginBottom: spacing.lg,
    ...shadow,
  },
});

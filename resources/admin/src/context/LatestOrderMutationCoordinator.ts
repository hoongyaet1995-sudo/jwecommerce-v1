export class LatestOrderMutationCoordinator {
  private readonly generationByOrder = new Map<string, number>();

  async run<T>(orderId: string, mutation: () => Promise<T>, commit: (value: T) => void): Promise<T | null> {
    const generation = (this.generationByOrder.get(orderId) ?? 0) + 1;
    this.generationByOrder.set(orderId, generation);

    try {
      const value = await mutation();
      if (this.generationByOrder.get(orderId) !== generation) return null;

      commit(value);
      return value;
    } catch (error) {
      if (this.generationByOrder.get(orderId) !== generation) return null;
      throw error;
    }
  }
}

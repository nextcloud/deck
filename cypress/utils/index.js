export const randHash = () => Math.random().toString(36).replace(/[^a-z]+/g, '').slice(0, 10)

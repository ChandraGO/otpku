import axios from "axios";

export const API = `${process.env.REACT_APP_BACKEND_URL}/api`;

export const http = axios.create({ baseURL: API, withCredentials: true });

export function errMsg(e) {
  const d = e?.response?.data?.detail;
  if (typeof d === "string") return d;
  if (Array.isArray(d)) return d.map((x) => x?.msg || JSON.stringify(x)).join(" ");
  if (d?.msg) return d.msg;
  return e?.message || "Terjadi kesalahan";
}

export const rupiah = (n) =>
  "Rp" + Number(n || 0).toLocaleString("id-ID", { maximumFractionDigits: 0 });

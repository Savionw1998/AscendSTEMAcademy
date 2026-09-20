/* Circuit Clash question bank — Ascend STEM Academy
   Each question: { q, a: [choices], c: correctIndex, why: explanation, lvl: 1|2|3 } */
window.CC_BANK = {
  math: {
    label: "Math",
    icon: "∑",
    items: [
      { q: "What is 7 × 8?", a: ["54", "56", "48", "64"], c: 1, why: "7 × 8 = 56.", lvl: 1 },
      { q: "What is 15% of 200?", a: ["15", "20", "30", "45"], c: 2, why: "0.15 × 200 = 30.", lvl: 1 },
      { q: "A square has a side of 9 cm. What is its perimeter?", a: ["18 cm", "27 cm", "36 cm", "81 cm"], c: 2, why: "Perimeter = 4 × 9 = 36 cm.", lvl: 1 },
      { q: "Solve for x:  3x + 7 = 25", a: ["4", "6", "8", "9"], c: 1, why: "3x = 18, so x = 6.", lvl: 2 },
      { q: "What is the slope of the line through (1, 2) and (3, 8)?", a: ["2", "3", "4", "6"], c: 1, why: "(8 − 2) / (3 − 1) = 6 / 2 = 3.", lvl: 2 },
      { q: "Area of a circle with radius 5 (use π ≈ 3.14)?", a: ["31.4", "78.5", "15.7", "157"], c: 1, why: "A = πr² = 3.14 × 25 = 78.5.", lvl: 2 },
      { q: "What is the median of 3, 7, 9, 4, 12?", a: ["4", "7", "9", "3"], c: 1, why: "Sorted: 3, 4, 7, 9, 12 — the middle value is 7.", lvl: 2 },
      { q: "Rolling two dice, what is the probability the sum is 7?", a: ["1/12", "1/6", "1/8", "1/4"], c: 1, why: "6 of the 36 outcomes sum to 7 → 1/6.", lvl: 2 },
      { q: "A right triangle has legs 9 and 12. How long is the hypotenuse?", a: ["13", "14", "15", "21"], c: 2, why: "√(81 + 144) = √225 = 15.", lvl: 3 },
      { q: "What is 2¹⁰?", a: ["512", "1000", "1024", "2048"], c: 2, why: "2¹⁰ = 1024.", lvl: 3 },
      { q: "What is the derivative of 3x²?", a: ["3x", "6x", "6x²", "x³"], c: 1, why: "d/dx (3x²) = 6x.", lvl: 3 },
      { q: "What is log₁₀(1000)?", a: ["2", "3", "10", "100"], c: 1, why: "10³ = 1000, so the log is 3.", lvl: 3 }
    ]
  },
  physics: {
    label: "Physics",
    icon: "⚛",
    items: [
      { q: "What is the SI unit of force?", a: ["Joule", "Watt", "Newton", "Pascal"], c: 2, why: "Force is measured in newtons (N).", lvl: 1 },
      { q: "A runner covers 100 m in 20 s. What is their average speed?", a: ["2 m/s", "5 m/s", "10 m/s", "20 m/s"], c: 1, why: "speed = distance / time = 100 / 20 = 5 m/s.", lvl: 1 },
      { q: "Near Earth's surface, acceleration due to gravity is about…", a: ["3.7 m/s²", "9.8 m/s²", "12 m/s²", "1.6 m/s²"], c: 1, why: "g ≈ 9.8 m/s² on Earth.", lvl: 1 },
      { q: "Newton's third law says forces come in pairs that are…", a: ["Equal and opposite", "Equal and identical", "Unequal and opposite", "Always zero"], c: 0, why: "Every action has an equal and opposite reaction.", lvl: 1 },
      { q: "What is the SI unit of power?", a: ["Newton", "Watt", "Joule", "Ampere"], c: 1, why: "Power is energy per second — watts.", lvl: 2 },
      { q: "Which formula gives kinetic energy?", a: ["mgh", "½mv²", "ma", "IR"], c: 1, why: "KE = ½mv².", lvl: 2 },
      { q: "A 2 kg cart moves at 3 m/s. What is its kinetic energy?", a: ["3 J", "6 J", "9 J", "18 J"], c: 2, why: "½ × 2 × 3² = 9 J.", lvl: 2 },
      { q: "A 12 V battery across a 4 Ω resistor. What current flows?", a: ["0.3 A", "3 A", "8 A", "48 A"], c: 1, why: "Ohm's law: I = V / R = 12 / 4 = 3 A.", lvl: 2 },
      { q: "Which of these changes if you travel to the Moon?", a: ["Your mass", "Your weight", "Your volume", "Your density"], c: 1, why: "Mass stays constant; weight depends on gravity.", lvl: 2 },
      { q: "The speed of light in a vacuum is about…", a: ["3 × 10⁶ m/s", "3 × 10⁸ m/s", "3 × 10¹⁰ m/s", "340 m/s"], c: 1, why: "c ≈ 3 × 10⁸ m/s.", lvl: 3 },
      { q: "For a wave at fixed speed, as frequency increases, wavelength…", a: ["Increases", "Decreases", "Stays the same", "Doubles"], c: 1, why: "v = fλ, so f and λ are inversely related.", lvl: 3 },
      { q: "Which unit measures work and energy?", a: ["Joule", "Watt", "Newton", "Hertz"], c: 0, why: "Work and energy are both measured in joules.", lvl: 2 }
    ]
  },
  chem: {
    label: "Chemistry",
    icon: "⚗",
    items: [
      { q: "What is the chemical symbol for gold?", a: ["Go", "Gd", "Au", "Ag"], c: 2, why: "Au, from the Latin aurum.", lvl: 1 },
      { q: "A solution with pH 7 is…", a: ["Acidic", "Basic", "Neutral", "Corrosive"], c: 2, why: "pH 7 is neutral — pure water.", lvl: 1 },
      { q: "NaCl is commonly known as…", a: ["Baking soda", "Table salt", "Sugar", "Bleach"], c: 1, why: "Sodium chloride is table salt.", lvl: 1 },
      { q: "Water boils at what temperature at sea level?", a: ["50 °C", "90 °C", "100 °C", "120 °C"], c: 2, why: "100 °C (212 °F) at 1 atm.", lvl: 1 },
      { q: "An element's atomic number equals its number of…", a: ["Neutrons", "Protons", "Electrons shells", "Isotopes"], c: 1, why: "Atomic number = proton count.", lvl: 2 },
      { q: "What is CO₂?", a: ["Carbon monoxide", "Carbon dioxide", "Calcium oxide", "Chlorine dioxide"], c: 1, why: "One carbon, two oxygens — carbon dioxide.", lvl: 1 },
      { q: "What charge does an electron carry?", a: ["Positive", "Negative", "Neutral", "It varies"], c: 1, why: "Electrons are negatively charged.", lvl: 1 },
      { q: "Horizontal rows on the periodic table are called…", a: ["Groups", "Periods", "Families", "Blocks"], c: 1, why: "Rows are periods; columns are groups.", lvl: 2 },
      { q: "An acid plus a base produces…", a: ["Salt and water", "Only gas", "A metal", "An isotope"], c: 0, why: "Neutralization yields a salt plus water.", lvl: 2 },
      { q: "Gas changing directly into a liquid is called…", a: ["Sublimation", "Condensation", "Evaporation", "Deposition"], c: 1, why: "Gas → liquid is condensation.", lvl: 2 },
      { q: "How many atoms total are in one molecule of H₂SO₄?", a: ["4", "6", "7", "8"], c: 2, why: "2 H + 1 S + 4 O = 7 atoms.", lvl: 3 },
      { q: "Which group of elements is the least reactive?", a: ["Alkali metals", "Halogens", "Noble gases", "Transition metals"], c: 2, why: "Noble gases have full outer shells.", lvl: 3 }
    ]
  },
  bio: {
    label: "Biology",
    icon: "🧬",
    items: [
      { q: "Which organelle is called the powerhouse of the cell?", a: ["Nucleus", "Ribosome", "Mitochondrion", "Vacuole"], c: 2, why: "Mitochondria produce most of the cell's ATP.", lvl: 1 },
      { q: "Which gas do plants take in for photosynthesis?", a: ["Oxygen", "Nitrogen", "Carbon dioxide", "Hydrogen"], c: 2, why: "Plants absorb CO₂ and release O₂.", lvl: 1 },
      { q: "Which organ pumps blood through the body?", a: ["Lungs", "Heart", "Liver", "Kidney"], c: 1, why: "The heart is the body's pump.", lvl: 1 },
      { q: "What is the largest organ of the human body?", a: ["Liver", "Brain", "Skin", "Lungs"], c: 2, why: "Skin is the largest organ.", lvl: 1 },
      { q: "In DNA, adenine (A) always pairs with…", a: ["Guanine", "Thymine", "Cytosine", "Uracil"], c: 1, why: "A pairs with T; G pairs with C.", lvl: 2 },
      { q: "Which structure do plant cells have that animal cells do not?", a: ["Cell wall", "Nucleus", "Membrane", "Ribosome"], c: 0, why: "Plant cells have a rigid cell wall.", lvl: 2 },
      { q: "The basic unit of heredity is the…", a: ["Cell", "Gene", "Protein", "Enzyme"], c: 1, why: "Genes carry inherited traits.", lvl: 2 },
      { q: "Which blood cells carry oxygen?", a: ["White blood cells", "Red blood cells", "Platelets", "Plasma cells"], c: 1, why: "Red blood cells carry oxygen via hemoglobin.", lvl: 1 },
      { q: "Cell division producing two identical cells is called…", a: ["Meiosis", "Mitosis", "Osmosis", "Diffusion"], c: 1, why: "Mitosis makes two identical daughter cells.", lvl: 2 },
      { q: "Photosynthesis happens inside which organelle?", a: ["Chloroplast", "Nucleus", "Lysosome", "Golgi body"], c: 0, why: "Chloroplasts hold chlorophyll.", lvl: 2 },
      { q: "In a food chain, grass is a…", a: ["Producer", "Consumer", "Decomposer", "Predator"], c: 0, why: "Plants make their own food — producers.", lvl: 1 },
      { q: "How many pairs of chromosomes does a human body cell have?", a: ["21", "22", "23", "46"], c: 2, why: "23 pairs — 46 chromosomes total.", lvl: 3 }
    ]
  },
  cs: {
    label: "Code & Logic",
    icon: "⌨",
    items: [
      { q: "What does CPU stand for?", a: ["Central Process Unit", "Central Processing Unit", "Computer Power Unit", "Control Program Unit"], c: 1, why: "Central Processing Unit.", lvl: 1 },
      { q: "How many bits are in one byte?", a: ["4", "8", "16", "32"], c: 1, why: "1 byte = 8 bits.", lvl: 1 },
      { q: "Which of these is a programming language?", a: ["Photoshop", "Python", "Firefox", "Linux"], c: 1, why: "Python is a programming language.", lvl: 1 },
      { q: "A Boolean value can be…", a: ["Any number", "True or false", "Any text", "Only zero"], c: 1, why: "Booleans are true or false.", lvl: 1 },
      { q: "What is a 'bug' in a program?", a: ["A feature", "An error", "A comment", "A file"], c: 1, why: "A bug is a defect causing wrong behavior.", lvl: 1 },
      { q: "What is the binary number 1011 in decimal?", a: ["9", "11", "13", "15"], c: 1, why: "8 + 0 + 2 + 1 = 11.", lvl: 2 },
      { q: "Which loop repeats while a condition stays true?", a: ["for-each", "while", "switch", "if"], c: 1, why: "A while loop runs as long as its condition holds.", lvl: 2 },
      { q: "HTML is mainly used to…", a: ["Style a page", "Structure a page", "Query a database", "Compile code"], c: 1, why: "HTML provides page structure; CSS styles it.", lvl: 2 },
      { q: "An algorithm is best described as…", a: ["A type of computer", "A step-by-step procedure", "A programming language", "A storage device"], c: 1, why: "It's a finite sequence of steps solving a problem.", lvl: 2 },
      { q: "What is 12 in binary?", a: ["1010", "1100", "1110", "1001"], c: 1, why: "8 + 4 = 12 → 1100.", lvl: 3 },
      { q: "On average, which sorting algorithm is faster on large lists?", a: ["Bubble sort", "Quicksort", "They tie", "Neither sorts"], c: 1, why: "Quicksort averages O(n log n); bubble sort is O(n²).", lvl: 3 },
      { q: "A variable is best described as…", a: ["Named storage for a value", "A permanent constant", "A kind of loop", "An error message"], c: 0, why: "A variable names a place that holds a value.", lvl: 1 }
    ]
  },
  eng: {
    label: "Engineering",
    icon: "⚙",
    items: [
      { q: "What is the first step of the engineering design process?", a: ["Build it", "Define the problem", "Sell it", "Test it"], c: 1, why: "You can't design a solution before defining the problem.", lvl: 1 },
      { q: "A ramp is an example of which simple machine?", a: ["Lever", "Pulley", "Inclined plane", "Screw"], c: 2, why: "A ramp is an inclined plane.", lvl: 1 },
      { q: "Which shape gives bridges the most rigidity?", a: ["Square", "Triangle", "Circle", "Hexagon"], c: 1, why: "Triangles don't deform without changing side lengths.", lvl: 1 },
      { q: "A prototype is…", a: ["The final product", "An early working model", "A blueprint only", "A sales pitch"], c: 1, why: "Prototypes are early models built to test ideas.", lvl: 1 },
      { q: "On a lever, the pivot point is called the…", a: ["Fulcrum", "Load", "Effort", "Axle"], c: 0, why: "The fulcrum is the pivot.", lvl: 2 },
      { q: "Pulling a cable end-to-end puts it under…", a: ["Compression", "Tension", "Torsion", "Shear"], c: 1, why: "Pulling forces create tension.", lvl: 2 },
      { q: "Which material is the best electrical conductor?", a: ["Rubber", "Glass", "Copper", "Wood"], c: 2, why: "Copper conducts electricity very well.", lvl: 1 },
      { q: "CAD stands for…", a: ["Computer Aided Design", "Circuit And Design", "Central Auto Drafting", "Computed Angle Drawing"], c: 0, why: "Computer Aided Design.", lvl: 2 },
      { q: "Why do engineers iterate on a design?", a: ["To use more materials", "To improve it after testing", "To delay the project", "To avoid testing"], c: 1, why: "Test results feed the next, better version.", lvl: 2 },
      { q: "A large gear drives a small gear. The small gear…", a: ["Spins faster", "Spins slower", "Spins the same", "Does not move"], c: 0, why: "Fewer teeth means more turns per drive rotation.", lvl: 3 },
      { q: "Which tool measures small thicknesses most precisely?", a: ["Tape measure", "Caliper", "Yardstick", "Protractor"], c: 1, why: "Calipers read to fractions of a millimeter.", lvl: 3 },
      { q: "Which is a renewable energy source?", a: ["Coal", "Natural gas", "Solar", "Diesel"], c: 2, why: "Sunlight is renewable; fossil fuels are not.", lvl: 1 }
    ]
  }
};
